<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_lesson_route_contract_uses_admin_auth_scoped_bindings_and_numeric_ids(): void
    {
        $expected = [
            'admin.education.courses.modules.lessons.index' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.create' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.store' => ['POST'],
            'admin.education.courses.modules.lessons.publication' => ['PATCH'],
            'admin.education.courses.modules.lessons.edit' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.update' => ['PATCH'],
            'admin.education.courses.modules.lessons.destroy' => ['DELETE'],
        ];

        foreach ($expected as $name => $methods) {
            $route = $this->namedRoute($name);
            $this->assertContains('admin.auth', $route->gatherMiddleware());
            $this->assertSame($methods, $route->methods());

            foreach (['course', 'module'] as $parameter) {
                $this->assertNumeric($route, $parameter, $name);
            }

            if (str_contains($name, '.publication') || str_contains($name, '.edit') || str_contains($name, '.update') || str_contains($name, '.destroy')) {
                $this->assertTrue($route->enforcesScopedBindings());
                $this->assertNumeric($route, 'lesson', $name);
            }
        }
    }

    public function test_guest_is_redirected_from_lesson_management(): void
    {
        [$course, $module] = $this->createHierarchy();

        $this->get(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        )->assertRedirect(route('admin.login'));
    }

    public function test_exact_permissions_are_required_for_lesson_actions(): void
    {
        [$course, $module] = $this->createHierarchy();
        $lesson = $this->createLesson($module);

        $viewOnly = $this->createAdminWithPermissions(['education.view']);

        $this->actingAs($viewOnly, 'admin')->post(
            route('admin.education.courses.modules.lessons.store', compact('course', 'module')),
            $this->validPayload()
        )->assertForbidden();

        $publishOnly = $this->createAdminWithPermissions(['education.publish']);

        $this->actingAs($publishOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.update', compact('course', 'module', 'lesson')),
            ['title' => 'Not Allowed']
        )->assertForbidden();

        $updateOnly = $this->createAdminWithPermissions(['education.update']);

        $this->actingAs($updateOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.publication', compact('course', 'module', 'lesson')),
            ['action' => 'PUBLISH']
        )->assertForbidden();
    }

    public function test_lesson_update_cannot_move_parent_or_change_publication_fields(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module] = $this->createHierarchy();
        $lesson = $this->createLesson($module);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.edit', compact('course', 'module', 'lesson')))
            ->patch(
                route('admin.education.courses.modules.lessons.update', compact('course', 'module', 'lesson')),
                [
                    'title' => 'Attempted Mutation',
                    'course_module_id' => 999,
                    'status' => Lesson::STATUS_PUBLISHED,
                    'published_at' => now()->toDateTimeString(),
                ]
            );

        $response->assertSessionHasErrors(['course_module_id', 'status', 'published_at']);
        $lesson->refresh();
        $this->assertSame($module->id, $lesson->course_module_id);
        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status);
    }

    public function test_cross_module_lesson_url_returns_not_found(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $moduleA] = $this->createHierarchy();

        $moduleB = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'OTHER-MOD',
            'slug' => 'other-module',
            'title' => 'Other Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 2,
        ]);

        $lesson = $this->createLesson($moduleB);

        $this->actingAs($admin, 'admin')->get(
            route('admin.education.courses.modules.lessons.edit', [
                'course' => $course,
                'module' => $moduleA,
                'lesson' => $lesson,
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
            'name' => 'Lesson Security Admin',
            'email' => 'lesson-sec-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'LESSON_SEC_'.strtoupper(Str::random(8)),
            'name' => 'Lesson Security Role',
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
            'code' => 'SEC-C-'.strtoupper(Str::random(5)),
            'slug' => 'sec-course-'.Str::lower(Str::random(7)),
            'title' => 'Security Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'SEC-M-'.strtoupper(Str::random(5)),
            'slug' => 'sec-module-'.Str::lower(Str::random(7)),
            'title' => 'Security Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module];
    }

    private function createLesson(CourseModule $module): Lesson
    {
        return Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'SEC-L-'.strtoupper(Str::random(5)),
            'slug' => 'sec-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Security Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'code' => 'SEC-NEW-L',
            'slug' => 'security-new-lesson',
            'title' => 'Security New Lesson',
            'sort_order' => 1,
        ];
    }
}
