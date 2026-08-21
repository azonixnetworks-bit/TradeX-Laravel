<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_routes_keep_auth_methods_scoped_bindings_and_numeric_constraints(): void
    {
        $expected = [
            'admin.education.courses.modules.lessons.assignments.index' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.assignments.create' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.assignments.store' => ['POST'],
            'admin.education.courses.modules.lessons.assignments.publication' => ['PATCH'],
            'admin.education.courses.modules.lessons.assignments.edit' => ['GET', 'HEAD'],
            'admin.education.courses.modules.lessons.assignments.update' => ['PATCH'],
            'admin.education.courses.modules.lessons.assignments.destroy' => ['DELETE'],
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
                $this->assertNumeric($route, 'assignment', $name);
            }
        }
    }

    public function test_guest_is_redirected_from_assignment_index(): void
    {
        [$course, $module, $lesson] = $this->createHierarchy();

        $this->get(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        )->assertRedirect(route('admin.login'));
    }

    public function test_exact_permissions_are_required_for_assignment_mutations(): void
    {
        [$course, $module, $lesson] = $this->createHierarchy();
        $assignment = $this->createAssignment($lesson);

        $viewOnly = $this->createAdminWithPermissions(['education.view']);
        $this->actingAs($viewOnly, 'admin')->post(
            route('admin.education.courses.modules.lessons.assignments.store', compact('course', 'module', 'lesson')),
            $this->validPayload()
        )->assertForbidden();

        $publishOnly = $this->createAdminWithPermissions(['education.publish']);
        $this->actingAs($publishOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.assignments.update', compact('course', 'module', 'lesson', 'assignment')),
            ['title' => 'Blocked']
        )->assertForbidden();

        $updateOnly = $this->createAdminWithPermissions(['education.update']);
        $this->actingAs($updateOnly, 'admin')->patch(
            route('admin.education.courses.modules.lessons.assignments.publication', compact('course', 'module', 'lesson', 'assignment')),
            ['action' => 'PUBLISH']
        )->assertForbidden();
    }

    public function test_assignment_request_rejects_learner_file_upload_fields_and_invalid_submission_method(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.assignments.create', compact('course', 'module', 'lesson')))
            ->post(
                route('admin.education.courses.modules.lessons.assignments.store', compact('course', 'module', 'lesson')),
                array_merge($this->validPayload(), [
                    'submission_method' => 'UPLOAD',
                    'submission_file' => 'forbidden.pdf',
                ])
            );

        $response->assertSessionHasErrors(['submission_method', 'submission_file']);
        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_assignment_metadata_update_cannot_move_parent_or_change_publication_state(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $assignment = $this->createAssignment($lesson);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.assignments.edit', compact('course', 'module', 'lesson', 'assignment')))
            ->patch(
                route('admin.education.courses.modules.lessons.assignments.update', compact('course', 'module', 'lesson', 'assignment')),
                [
                    'title' => 'Attempt',
                    'lesson_id' => 999,
                    'status' => Assignment::STATUS_PUBLISHED,
                    'published_at' => now()->toDateTimeString(),
                ]
            );

        $response->assertSessionHasErrors(['lesson_id', 'status', 'published_at']);
        $assignment->refresh();
        $this->assertSame($lesson->id, $assignment->lesson_id);
        $this->assertSame(Assignment::STATUS_DRAFT, $assignment->status);
    }

    public function test_cross_lesson_assignment_idor_is_rejected(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lessonA] = $this->createHierarchy();

        $lessonB = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'ASSIGN-OTHER',
            'slug' => 'assignment-other-lesson',
            'title' => 'Other Assignment Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 2,
        ]);

        $assignment = $this->createAssignment($lessonB);

        $this->actingAs($admin, 'admin')->get(
            route('admin.education.courses.modules.lessons.assignments.edit', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lessonA,
                'assignment' => $assignment,
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
            'name' => 'Assignment Security Admin',
            'email' => 'assignment-sec-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ASSIGN_SEC_'.strtoupper(Str::random(8)),
            'name' => 'Assignment Security Role',
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
            'code' => 'ASEC-C-'.strtoupper(Str::random(5)),
            'slug' => 'asec-course-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Security Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'ASEC-M-'.strtoupper(Str::random(5)),
            'slug' => 'asec-module-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Security Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'ASEC-L-'.strtoupper(Str::random(5)),
            'slug' => 'asec-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Security Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module, $lesson];
    }

    private function createAssignment(Lesson $lesson): Assignment
    {
        return Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Assignment Security Fixture',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' => 'Send externally.',
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'sort_order' => 1,
        ]);
    }

    private function validPayload(): array
    {
        return [
            'title' => 'Valid Assignment',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'is_required' => true,
            'sort_order' => 1,
        ];
    }
}
