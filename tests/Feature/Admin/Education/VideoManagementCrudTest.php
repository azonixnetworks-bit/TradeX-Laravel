<?php

namespace Tests\Feature\Admin\Education;

use App\Jobs\ProcessVideo;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_video_as_draft_pending_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.store', compact('course', 'module', 'lesson')),
            [
                'title' => 'Protected Lesson Video',
                'short_description' => 'Video summary',
                'description' => 'Video description',
                'storage_disk' => 'private',
                'source_path' => 'courses/test/lesson/video.mp4',
                'mime_type' => 'video/mp4',
                'is_downloadable' => 0,
                'sort_order' => 1,
            ]
        );

        $video = LessonVideo::query()->where('lesson_id', $lesson->id)->firstOrFail();

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        );

        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->status);
        $this->assertSame(LessonVideo::PROCESSING_PENDING, $video->processing_status);
        $this->assertNull($video->published_at);
        $this->assertFalse($video->is_downloadable);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'VIDEO_CREATED',
            'target_type' => 'LESSON_VIDEO',
            'target_id' => $video->id,
        ]);
    }

    public function test_authorized_admin_can_update_video_metadata_without_processing_or_publication_change(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson);

        $response = $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.update', compact('course', 'module', 'lesson', 'video')),
            [
                'title' => 'Updated Video',
                'hls_master_path' => 'protected/hls/master.m3u8',
                'streaming_asset_key' => 'asset-123',
                'duration_seconds' => 540,
                'sort_order' => 4,
            ]
        );

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        );

        $video->refresh();
        $this->assertSame('Updated Video', $video->title);
        $this->assertSame(LessonVideo::PROCESSING_PENDING, $video->processing_status);
        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'VIDEO_UPDATED',
            'target_id' => $video->id,
        ]);
    }

    public function test_video_cannot_publish_until_processing_ready_and_playback_reference_exists(): void
    {
        $admin = $this->createAdminWithPermissions(['education.publish']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.publication', compact('course', 'module', 'lesson', 'video')),
            ['action' => 'PUBLISH']
        )->assertStatus(422);

        $video->refresh();
        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->status);

        $video->forceFill([
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processed_at' => now(),
        ])->save();

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.publication', compact('course', 'module', 'lesson', 'video')),
            ['action' => 'PUBLISH']
        )->assertStatus(422);

        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->fresh()->status);
    }

    public function test_ready_video_with_protected_playback_reference_can_publish_and_unpublish(): void
    {
        $admin = $this->createAdminWithPermissions(['education.publish']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processed_at' => now(),
            'hls_master_path' => 'protected/hls/master.m3u8',
        ]);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.publication', compact('course', 'module', 'lesson', 'video')),
            ['action' => 'PUBLISH', 'reason' => 'Processing verified']
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        );

        $video->refresh();
        $this->assertSame(LessonVideo::STATUS_PUBLISHED, $video->status);
        $this->assertNotNull($video->published_at);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.publication', compact('course', 'module', 'lesson', 'video')),
            ['action' => 'UNPUBLISH']
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        );

        $video->refresh();
        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->status);
        $this->assertNull($video->published_at);

        $this->assertDatabaseHas('audit_logs', ['action' => 'VIDEO_PUBLISHED', 'target_id' => $video->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'VIDEO_UNPUBLISHED', 'target_id' => $video->id]);
    }

    public function test_published_or_processing_video_cannot_be_deleted_but_safe_draft_can(): void
    {
        $admin = $this->createAdminWithPermissions(['education.delete']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $published = $this->createVideo($lesson, [
            'status' => LessonVideo::STATUS_PUBLISHED,
            'published_at' => now(),
            'processing_status' => LessonVideo::PROCESSING_READY,
            'hls_master_path' => 'protected/a/master.m3u8',
        ]);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.videos.destroy', [
                'course' => $course, 'module' => $module, 'lesson' => $lesson, 'video' => $published,
            ])
        )->assertStatus(422);

        $processing = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_PROCESSING,
        ]);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.videos.destroy', [
                'course' => $course, 'module' => $module, 'lesson' => $lesson, 'video' => $processing,
            ])
        )->assertStatus(422);

        $safe = $this->createVideo($lesson);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.videos.destroy', [
                'course' => $course, 'module' => $module, 'lesson' => $lesson, 'video' => $safe,
            ])
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        );

        $this->assertSoftDeleted('lesson_videos', ['id' => $safe->id]);
    }

    public function test_video_list_displays_live_processing_progress_component(): void
    {
        $admin = $this->createAdminWithPermissions(['education.view']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_PROCESSING,
            'processing_progress' => 47,
            'processing_stage' => 'Encoding 720p HLS rendition (1/2).',
        ]);

        $this->actingAs($admin, 'admin')->get(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        )
            ->assertOk()
            ->assertSee('data-video-processing', false)
            ->assertSee('47%')
            ->assertSee('Encoding 720p HLS rendition (1/2).')
            ->assertSee(route(
                'admin.education.courses.modules.lessons.videos.processing-status',
                compact('course', 'module', 'lesson', 'video')
            ), false);
    }

    public function test_authorized_admin_can_poll_video_processing_status(): void
    {
        $admin = $this->createAdminWithPermissions(['education.view']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processing_progress' => 100,
            'processing_stage' => 'HLS processing completed successfully.',
            'hls_master_path' => 'protected/hls/master.m3u8',
            'processed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')->getJson(
            route('admin.education.courses.modules.lessons.videos.processing-status', compact('course', 'module', 'lesson', 'video'))
        )
            ->assertOk()
            ->assertJson([
                'status' => LessonVideo::PROCESSING_READY,
                'progress' => 100,
                'stage' => 'HLS processing completed successfully.',
                'complete' => true,
                'failed' => false,
            ]);
    }

    public function test_processing_status_exposes_live_hls_telemetry(): void
    {
        $admin = $this->createAdminWithPermissions(['education.view']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_PROCESSING,
            'processing_progress' => 43,
            'processing_stage' => 'Encoding 720p HLS rendition (2/3) — 57%.',
            'processing_detail_json' => [
                'current_step' => 'ENCODING_HLS',
                'source_file' => 'lesson-video.mp4',
                'rendition' => '720p',
                'rendition_index' => 2,
                'rendition_total' => 3,
                'rendition_percent' => 57,
                'video_position_seconds' => 1398,
                'duration_seconds' => 2452,
                'speed' => '1.43x',
                'elapsed_seconds' => 972,
                'estimated_remaining_seconds' => 733,
            ],
            'processing_started_at' => now()->subMinutes(16),
            'processing_heartbeat_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')->getJson(
            route(
                'admin.education.courses.modules.lessons.videos.processing-status',
                compact('course', 'module', 'lesson', 'video')
            )
        )
            ->assertOk()
            ->assertJson([
                'status' => LessonVideo::PROCESSING_PROCESSING,
                'progress' => 43,
                'stalled' => false,
                'detail' => [
                    'current_step' => 'ENCODING_HLS',
                    'source_file' => 'lesson-video.mp4',
                    'rendition' => '720p',
                    'rendition_index' => 2,
                    'rendition_total' => 3,
                    'rendition_percent' => 57,
                    'speed' => '1.43x',
                ],
            ])
            ->assertJsonPath('complete', false)
            ->assertJsonPath('failed', false);
    }

    public function test_processing_status_marks_stale_worker_heartbeat_as_stalled(): void
    {
        $admin = $this->createAdminWithPermissions(['education.view']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_PROCESSING,
            'processing_progress' => 38,
            'processing_stage' => 'Encoding 360p HLS rendition (1/3) — 18%.',
            'processing_detail_json' => [
                'current_step' => 'ENCODING_HLS',
                'rendition' => '360p',
                'rendition_percent' => 18,
            ],
            'processing_started_at' => now()->subMinutes(8),
            'processing_heartbeat_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin, 'admin')->getJson(
            route(
                'admin.education.courses.modules.lessons.videos.processing-status',
                compact('course', 'module', 'lesson', 'video')
            )
        )
            ->assertOk()
            ->assertJsonPath('status', LessonVideo::PROCESSING_PROCESSING)
            ->assertJsonPath('stalled', true)
            ->assertJsonPath('terminal', false);
    }

    public function test_failed_video_can_retry_using_existing_source_and_same_database_record(): void
    {
        Queue::fake();
        Storage::fake('private');
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        Storage::disk('private')->put('protected/source/video.mp4', 'existing-video');
        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_FAILED,
            'processing_progress' => 18,
            'processing_stage' => 'Processing failed. Review the error and retry.',
            'processing_error' => 'FFprobe could not inspect the uploaded video.',
            'processed_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.retry-processing', compact('course', 'module', 'lesson', 'video'))
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.edit', compact('course', 'module', 'lesson', 'video'))
        );

        $video->refresh();
        $this->assertSame(LessonVideo::PROCESSING_PENDING, $video->processing_status);
        $this->assertSame(3, $video->processing_progress);
        $this->assertSame('Retry queued. Waiting for the background video worker.', $video->processing_stage);
        $this->assertNull($video->processing_error);
        $this->assertNull($video->processed_at);
        $this->assertDatabaseCount('lesson_videos', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'VIDEO_PROCESSING_RETRIED',
            'target_id' => $video->id,
        ]);
        Queue::assertPushed(ProcessVideo::class, fn (ProcessVideo $job): bool => $job->videoId === $video->id
            && $job->connection === config('tradex.media.queue_connection'));
    }

    public function test_ready_video_with_missing_playback_reference_can_repair_using_existing_source(): void
    {
        Queue::fake();
        Storage::fake('private');

        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();

        Storage::disk('private')->put('protected/source/video.mp4', 'existing-video');

        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processing_progress' => 100,
            'processing_stage' => 'HLS processing completed successfully.',
            'processed_at' => now(),
            'hls_master_path' => null,
            'streaming_asset_key' => null,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.retry-processing', compact('course', 'module', 'lesson', 'video'))
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.videos.edit', compact('course', 'module', 'lesson', 'video'))
        );

        $video->refresh();

        $this->assertSame(LessonVideo::PROCESSING_PENDING, $video->processing_status);
        $this->assertSame(3, $video->processing_progress);
        $this->assertSame(
            'Protected HLS repair queued. Waiting for the background video worker.',
            $video->processing_stage
        );
        $this->assertNull($video->hls_master_path);
        $this->assertNull($video->processed_at);

        Queue::assertPushed(
            ProcessVideo::class,
            fn (ProcessVideo $job): bool => $job->videoId === $video->id
                && $job->connection === config('tradex.media.queue_connection')
        );
    }

    public function test_retry_is_rejected_when_original_source_is_missing(): void
    {
        Queue::fake();
        Storage::fake('private');
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson, [
            'processing_status' => LessonVideo::PROCESSING_FAILED,
        ]);

        $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.retry-processing', compact('course', 'module', 'lesson', 'video'))
        )->assertSessionHasErrors('video');

        $this->assertSame(LessonVideo::PROCESSING_FAILED, $video->fresh()->processing_status);
        Queue::assertNothingPushed();
    }

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Video CRUD Admin',
            'email' => 'video-crud-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'VIDEO_CRUD_'.strtoupper(Str::random(8)),
            'name' => 'Video CRUD Role',
            'is_system' => false,
            'is_active' => true,
        ]);
        $admin->roles()->attach($role->id);

        foreach ($permissions as $code) {
            $permission = AdminPermission::query()->create([
                'code' => $code,
                'name' => $code,
                'module' => 'EDUCATION',
            ]);
            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }

    private function createHierarchy(): array
    {
        $course = Course::query()->create([
            'code' => 'VIDEO-C-'.strtoupper(Str::random(5)),
            'slug' => 'video-course-'.Str::lower(Str::random(7)),
            'title' => 'Video Parent Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'VIDEO-M-'.strtoupper(Str::random(5)),
            'slug' => 'video-module-'.Str::lower(Str::random(7)),
            'title' => 'Video Parent Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'VIDEO-L-'.strtoupper(Str::random(5)),
            'slug' => 'video-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Video Parent Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module, $lesson];
    }

    private function createVideo(Lesson $lesson, array $overrides = []): LessonVideo
    {
        return LessonVideo::query()->create(array_merge([
            'lesson_id' => $lesson->id,
            'title' => 'Video Fixture',
            'storage_disk' => 'private',
            'source_path' => 'protected/source/video.mp4',
            'processing_status' => LessonVideo::PROCESSING_PENDING,
            'status' => LessonVideo::STATUS_DRAFT,
            'published_at' => null,
            'is_downloadable' => false,
            'sort_order' => 1,
        ], $overrides));
    }
}
