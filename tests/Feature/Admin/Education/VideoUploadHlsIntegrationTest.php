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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoUploadHlsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_uploads_video_to_private_storage_and_hls_job_is_queued(): void
    {
        Storage::fake('private');
        Queue::fake();

        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $upload = UploadedFile::fake()->create(
            'lesson-video.mp4',
            2048,
            'video/mp4'
        );

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.education.courses.modules.lessons.videos.store',
                    compact('course', 'module', 'lesson')
                ),
                [
                    'title' => 'ADMIN-03A Uploaded Video',
                    'short_description' => 'Protected HLS source',
                    'storage_disk' => 'private',
                    'video_file' => $upload,
                    'is_downloadable' => 0,
                    'sort_order' => 1,
                ]
            );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.lessons.videos.index',
                compact('course', 'module', 'lesson')
            )
        );

        $video = LessonVideo::query()
            ->where('lesson_id', $lesson->id)
            ->firstOrFail();

        $this->assertSame(
            LessonVideo::PROCESSING_PENDING,
            $video->processing_status
        );

        $this->assertSame(
            LessonVideo::STATUS_DRAFT,
            $video->status
        );

        $this->assertSame(
            'private',
            $video->storage_disk
        );

        $this->assertStringStartsWith(
            'education/videos/'.$video->uuid.'/source/source.',
            $video->source_path
        );

        Storage::disk('private')
            ->assertExists(
                $video->source_path
            );

        Queue::assertPushed(
            ProcessVideo::class,
            fn (ProcessVideo $job): bool =>
                $job->videoId === $video->id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' => $admin->id,
                'action' => 'VIDEO_CREATED',
                'target_type' => 'LESSON_VIDEO',
                'target_id' => $video->id,
            ]
        );
    }

    public function test_authorized_admin_imports_private_cpanel_video_and_hls_job_is_queued(): void
    {
        Storage::fake('private');
        Queue::fake();

        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();
        Storage::disk('private')->put('video-imports/lesson-01.mp4', 'private-video-bytes');

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.store', compact('course', 'module', 'lesson')),
            [
                'title' => 'Imported cPanel Video',
                'storage_disk' => 'private',
                'import_source_path' => 'video-imports/lesson-01.mp4',
            ]
        );

        $response->assertRedirect(route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson')));
        $video = LessonVideo::query()->where('lesson_id', $lesson->id)->firstOrFail();

        $this->assertStringStartsWith('education/videos/'.$video->uuid.'/source/source.mp4', $video->source_path);
        Storage::disk('private')->assertExists($video->source_path);
        Storage::disk('private')->assertExists('video-imports/lesson-01.mp4');
        Queue::assertPushed(ProcessVideo::class, fn (ProcessVideo $job): bool => $job->videoId === $video->id);
    }

    public function test_cpanel_import_rejects_paths_outside_private_video_imports_inbox(): void
    {
        Storage::fake('private');
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')->from(
            route('admin.education.courses.modules.lessons.videos.create', compact('course', 'module', 'lesson'))
        )->post(route('admin.education.courses.modules.lessons.videos.store', compact('course', 'module', 'lesson')), [
            'title' => 'Invalid import',
            'storage_disk' => 'private',
            'import_source_path' => 'education/videos/secret.mp4',
        ]);

        $response->assertSessionHasErrors('import_source_path');
        $this->assertDatabaseCount('lesson_videos', 0);
    }

    public function test_real_upload_cannot_forge_worker_managed_hls_fields(): void
    {
        Storage::fake('private');
        Queue::fake();

        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this
            ->actingAs($admin, 'admin')
            ->from(
                route(
                    'admin.education.courses.modules.lessons.videos.create',
                    compact('course', 'module', 'lesson')
                )
            )
            ->post(
                route(
                    'admin.education.courses.modules.lessons.videos.store',
                    compact('course', 'module', 'lesson')
                ),
                [
                    'title' => 'Forged Pipeline Metadata',
                    'storage_disk' => 'private',
                    'video_file' => UploadedFile::fake()->create(
                        'lesson.mp4',
                        1024,
                        'video/mp4'
                    ),
                    'hls_master_path' => 'forged/master.m3u8',
                    'thumbnail_path' => 'forged/thumb.jpg',
                    'duration_seconds' => 999,
                ]
            );

        $response->assertSessionHasErrors([
            'hls_master_path',
            'thumbnail_path',
            'duration_seconds',
        ]);

        $this->assertDatabaseCount(
            'lesson_videos',
            0
        );
    }

    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN-03A Upload Admin',
            'email' => 'admin03a-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN03A_'.strtoupper(Str::random(8)),
            'name' => 'ADMIN-03A Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach(
            $role->id
        );

        foreach ($permissions as $code) {
            $permission = AdminPermission::query()->create([
                'code' => $code,
                'name' => $code,
                'module' => 'EDUCATION',
            ]);

            $role->permissions()->attach(
                $permission->id
            );
        }

        return $admin->fresh();
    }

    private function createHierarchy(): array
    {
        $course = Course::query()->create([
            'code' => 'A03A-C-'.strtoupper(Str::random(5)),
            'slug' => 'a03a-course-'.Str::lower(Str::random(7)),
            'title' => 'ADMIN-03A Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'A03A-M-'.strtoupper(Str::random(5)),
            'slug' => 'a03a-module-'.Str::lower(Str::random(7)),
            'title' => 'ADMIN-03A Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'A03A-L-'.strtoupper(Str::random(5)),
            'slug' => 'a03a-lesson-'.Str::lower(Str::random(7)),
            'title' => 'ADMIN-03A Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [
            $course,
            $module,
            $lesson,
        ];
    }
}
