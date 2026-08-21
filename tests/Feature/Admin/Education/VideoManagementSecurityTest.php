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
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_routes_keep_auth_methods_scoped_bindings_and_numeric_constraints(): void
    {
        $expected = [
            'admin.education.courses.modules.lessons.videos.index' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.videos.create' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.videos.store' => ['POST'],
            'admin.education.courses.modules.lessons.videos.publication' => ['PATCH'],
            'admin.education.courses.modules.lessons.videos.edit' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.videos.update' => ['PATCH'],
            'admin.education.courses.modules.lessons.videos.destroy' => ['DELETE'],
        ];

        foreach ($expected as $name => $methods) {
            $route = $this->namedRoute($name);
            $this->assertContains('admin.auth', $route->gatherMiddleware());
            $this->assertSame($methods, $route->methods());
            $this->assertTrue($route->enforcesScopedBindings());

            foreach (['course', 'module', 'lesson'] as $parameter) {
                $this->assertNumeric($route, $parameter, $name);
            }

            if (str_contains($name, '.publication') || str_contains($name, '.edit') || str_contains($name, '.update') || str_contains($name, '.destroy')) {
                $this->assertNumeric($route, 'video', $name);
            }
        }
    }

    public function test_guest_is_redirected_from_video_index(): void
    {
        [$course, $module, $lesson] = $this->createHierarchy();

        $this->get(
            route('admin.education.courses.modules.lessons.videos.index', compact('course', 'module', 'lesson'))
        )->assertRedirect(route('admin.login'));
    }

    public function test_exact_permissions_are_required_for_video_mutations(): void
    {
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson);

        $viewOnly = $this->createAdminWithPermissions(['education.view']);
        $this->actingAs($viewOnly, 'admin')->post(
            route('admin.education.courses.modules.lessons.videos.store', compact('course', 'module', 'lesson')),
            $this->validPayload()
        )->assertForbidden();

        $publishOnly = $this->createAdminWithPermissions(['education.publish']);
        $this->actingAs($publishOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.update', compact('course', 'module', 'lesson', 'video')),
            ['title' => 'Blocked']
        )->assertForbidden();

        $updateOnly = $this->createAdminWithPermissions(['education.update']);
        $this->actingAs($updateOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.videos.publication', compact('course', 'module', 'lesson', 'video')),
            ['action' => 'PUBLISH']
        )->assertForbidden();
    }

    public function test_public_or_signed_http_paths_are_rejected(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.videos.create', compact('course', 'module', 'lesson')))
            ->post(
                route('admin.education.courses.modules.lessons.videos.store', compact('course', 'module', 'lesson')),
                array_merge($this->validPayload(), [
                    'source_path' => 'https://cdn.example.test/signed/video.mp4?token=secret',
                    'hls_master_path' => 'https://cdn.example.test/master.m3u8',
                ])
            );

        $response->assertSessionHasErrors(['source_path', 'hls_master_path']);
        $this->assertDatabaseCount('lesson_videos', 0);
    }

    public function test_admin_cannot_forge_processing_or_publication_state_through_metadata_update(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $video = $this->createVideo($lesson);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.videos.edit', compact('course', 'module', 'lesson', 'video')))
            ->patch(
                route('admin.education.courses.modules.lessons.videos.update', compact('course', 'module', 'lesson', 'video')),
                [
                    'title' => 'Attempt',
                    'processing_status' => LessonVideo::PROCESSING_READY,
                    'processed_at' => now()->toDateTimeString(),
                    'status' => LessonVideo::STATUS_PUBLISHED,
                    'published_at' => now()->toDateTimeString(),
                ]
            );

        $response->assertSessionHasErrors([
            'processing_status',
            'processed_at',
            'status',
            'published_at',
        ]);

        $video->refresh();
        $this->assertSame(LessonVideo::PROCESSING_PENDING, $video->processing_status);
        $this->assertSame(LessonVideo::STATUS_DRAFT, $video->status);
    }

    public function test_cross_lesson_video_idor_is_rejected(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lessonA] = $this->createHierarchy();

        $lessonB = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'OTHER-LESSON',
            'slug' => 'other-video-lesson',
            'title' => 'Other Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 2,
        ]);

        $video = $this->createVideo($lessonB);

        $this->actingAs($admin, 'admin')->get(
            route('admin.education.courses.modules.lessons.videos.edit', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lessonA,
                'video' => $video,
            ])
        )->assertNotFound();
    }

    private function namedRoute(string $name): LaravelRoute
    {
        $route = Route::getRoutes()->getByName($name);
        $this->assertInstanceOf(LaravelRoute::class, $route, "Missing route {$name}");
        return $route;
    }

    private function assertNumeric(LaravelRoute $route, string $parameter, string $name): void
    {
        $pattern = $route->wheres[$parameter] ?? null;
        $this->assertIsString($pattern, "Route {$name} must constrain {$parameter}");
        $this->assertSame(1, preg_match('~^(?:'.$pattern.')$~', '123'));
        $this->assertSame(0, preg_match('~^(?:'.$pattern.')$~', 'abc'));
    }

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Video Security Admin',
            'email' => 'video-sec-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'VIDEO_SEC_'.strtoupper(Str::random(8)),
            'name' => 'Video Security Role',
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
            'code' => 'VSEC-C-'.strtoupper(Str::random(5)),
            'slug' => 'vsec-course-'.Str::lower(Str::random(7)),
            'title' => 'Video Security Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'VSEC-M-'.strtoupper(Str::random(5)),
            'slug' => 'vsec-module-'.Str::lower(Str::random(7)),
            'title' => 'Video Security Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'VSEC-L-'.strtoupper(Str::random(5)),
            'slug' => 'vsec-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Video Security Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module, $lesson];
    }

    private function createVideo(Lesson $lesson): LessonVideo
    {
        return LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Video Security Fixture',
            'storage_disk' => 'private',
            'source_path' => 'protected/source.mp4',
            'processing_status' => LessonVideo::PROCESSING_PENDING,
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'title' => 'Valid Video',
            'storage_disk' => 'private',
            'source_path' => 'protected/valid.mp4',
            'sort_order' => 1,
        ];
    }
}
