<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoProtectedPlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_protected_playback_routes_keep_admin_auth_scoped_bindings_and_numeric_constraints(): void
    {
        $expected = [
            'admin.education.courses.modules.lessons.videos.preview',
            'admin.education.courses.modules.lessons.videos.media',
        ];

        foreach ($expected as $name) {
            $route = $this->namedRoute(
                $name
            );

            $this->assertContains(
                'admin.auth',
                $route->gatherMiddleware()
            );

            $this->assertTrue(
                $route->enforcesScopedBindings()
            );

            foreach ([
                'course',
                'module',
                'lesson',
                'video',
            ] as $parameter) {
                $this->assertNumeric(
                    $route,
                    $parameter,
                    $name
                );
            }
        }
    }

    public function test_guest_cannot_open_admin_hls_preview(): void
    {
        Storage::fake(
            'private'
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = $this->createReadyVideo(
            $lesson
        );

        $this->writeHlsFixture(
            $video
        );

        $this->get(
            route(
                'admin.education.courses.modules.lessons.videos.preview',
                compact(
                    'course',
                    'module',
                    'lesson',
                    'video'
                )
            )
        )->assertRedirect(
            route(
                'admin.login'
            )
        );
    }

    public function test_admin_requires_education_view_permission_for_preview(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            []
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = $this->createReadyVideo(
            $lesson
        );

        $this->writeHlsFixture(
            $video
        );

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.preview',
                compact(
                    'course',
                    'module',
                    'lesson',
                    'video'
                )
            )
        )->assertForbidden();
    }

    public function test_authorized_admin_can_preview_ready_draft_hls_without_publishing_it(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            [
                'education.view',
            ]
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = $this->createReadyVideo(
            $lesson
        );

        $this->writeHlsFixture(
            $video
        );

        $response = $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.preview',
                compact(
                    'course',
                    'module',
                    'lesson',
                    'video'
                )
            )
        );

        $response
            ->assertOk()
            ->assertSeeText(
                'Protected HLS Preview'
            )
            ->assertSeeText(
                'ADMIN PREVIEW'
            );

        $this->assertSame(
            LessonVideo::STATUS_DRAFT,
            $video->fresh()->status
        );
    }

    public function test_master_and_variant_manifests_are_rewritten_to_authenticated_media_routes(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            [
                'education.view',
            ]
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = $this->createReadyVideo(
            $lesson
        );

        $this->writeHlsFixture(
            $video
        );

        $master = $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.media',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                    'video' => $video,
                    'path' => 'master.m3u8',
                ]
            )
        );

        $master
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'application/vnd.apple.mpegurl; charset=UTF-8'
            );

        $this->assertStringContainsString(
            '/media/360p/index.m3u8',
            $master->getContent()
        );

        $this->assertStringNotContainsString(
            'education/videos/'.$video->uuid.'/hls/',
            $master->getContent()
        );

        $variant = $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.media',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                    'video' => $video,
                    'path' => '360p/index.m3u8',
                ]
            )
        );

        $variant->assertOk();

        $this->assertStringContainsString(
            '/media/360p/segment_00000.ts',
            $variant->getContent()
        );
    }

    public function test_hls_segment_is_served_with_private_no_store_headers(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            [
                'education.view',
            ]
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = $this->createReadyVideo(
            $lesson
        );

        $this->writeHlsFixture(
            $video
        );

        $response = $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.media',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lesson,
                    'video' => $video,
                    'path' => '360p/segment_00000.ts',
                ]
            )
        );

        $response
            ->assertOk()
            ->assertHeader(
                'Content-Type',
                'video/mp2t'
            );

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get(
                'Cache-Control'
            )
        );

        $this->assertSame(
            'fake-ts-segment',
            $response->getContent()
        );
    }

    public function test_cross_lesson_video_preview_is_rejected(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            [
                'education.view',
            ]
        );

        [
            $course,
            $module,
            $lessonA,
        ] = $this->createHierarchy();

        $lessonB = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'A03B-OTHER-L',
            'slug' => 'admin03b-other-lesson',
            'title' => 'ADMIN-03B Other Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 2,
        ]);

        $video = $this->createReadyVideo(
            $lessonB
        );

        $this->writeHlsFixture(
            $video
        );

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.preview',
                [
                    'course' => $course,
                    'module' => $module,
                    'lesson' => $lessonA,
                    'video' => $video,
                ]
            )
        )->assertNotFound();
    }

    public function test_pending_video_cannot_be_previewed(): void
    {
        Storage::fake(
            'private'
        );

        $admin = $this->createAdminWithPermissions(
            [
                'education.view',
            ]
        );

        [
            $course,
            $module,
            $lesson,
        ] = $this->createHierarchy();

        $video = LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Pending Video',
            'storage_disk' => 'private',
            'source_path' => 'protected/source.mp4',
            'hls_master_path' => null,
            'processing_status' => LessonVideo::PROCESSING_PENDING,
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )->get(
            route(
                'admin.education.courses.modules.lessons.videos.preview',
                compact(
                    'course',
                    'module',
                    'lesson',
                    'video'
                )
            )
        )->assertStatus(
            422
        );
    }

    private function namedRoute(
        string $name
    ): LaravelRoute {
        $route = Route::getRoutes()
            ->getByName(
                $name
            );

        $this->assertInstanceOf(
            LaravelRoute::class,
            $route,
            "Missing route {$name}"
        );

        return $route;
    }

    private function assertNumeric(
        LaravelRoute $route,
        string $parameter,
        string $name
    ): void {
        $pattern = $route->wheres[
            $parameter
        ] ?? null;

        $this->assertIsString(
            $pattern,
            "Route {$name} must constrain {$parameter}"
        );

        $this->assertSame(
            1,
            preg_match(
                '~^(?:'.$pattern.')$~',
                '123'
            )
        );

        $this->assertSame(
            0,
            preg_match(
                '~^(?:'.$pattern.')$~',
                'abc'
            )
        );
    }

    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN-03B Playback Admin',
            'email' => 'admin03b-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN03B_'.strtoupper(
                Str::random(8)
            ),
            'name' => 'ADMIN-03B Playback Role',
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
            'code' => 'A03B-C-'.strtoupper(
                Str::random(5)
            ),
            'slug' => 'a03b-course-'.Str::lower(
                Str::random(7)
            ),
            'title' => 'ADMIN-03B Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'A03B-M-'.strtoupper(
                Str::random(5)
            ),
            'slug' => 'a03b-module-'.Str::lower(
                Str::random(7)
            ),
            'title' => 'ADMIN-03B Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'A03B-L-'.strtoupper(
                Str::random(5)
            ),
            'slug' => 'a03b-lesson-'.Str::lower(
                Str::random(7)
            ),
            'title' => 'ADMIN-03B Lesson',
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

    private function createReadyVideo(
        Lesson $lesson
    ): LessonVideo {
        $video = LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'ADMIN-03B Ready Video',
            'storage_disk' => 'private',
            'source_path' => 'education/source/video.mp4',
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processed_at' => now(),
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);

        $video->forceFill([
            'hls_master_path' => 'education/videos/'
                .$video->uuid
                .'/hls/master.m3u8',
        ])->save();

        return $video->fresh();
    }

    private function writeHlsFixture(
        LessonVideo $video
    ): void {
        $root = 'education/videos/'
            .$video->uuid
            .'/hls';

        Storage::disk(
            'private'
        )->put(
            $root.'/master.m3u8',
            "#EXTM3U\n"
            ."#EXT-X-VERSION:3\n"
            ."#EXT-X-STREAM-INF:BANDWIDTH=1000000,RESOLUTION=640x360\n"
            ."360p/index.m3u8\n"
        );

        Storage::disk(
            'private'
        )->put(
            $root.'/360p/index.m3u8',
            "#EXTM3U\n"
            ."#EXT-X-TARGETDURATION:6\n"
            ."#EXTINF:6.0,\n"
            ."segment_00000.ts\n"
            ."#EXT-X-ENDLIST\n"
        );

        Storage::disk(
            'private'
        )->put(
            $root.'/360p/segment_00000.ts',
            'fake-ts-segment'
        );
    }
}
