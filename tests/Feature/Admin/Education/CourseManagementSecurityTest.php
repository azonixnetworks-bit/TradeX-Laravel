<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_routes_require_admin_auth_and_keep_expected_methods(): void
    {
        $expected = [
            'admin.education.courses.index' => ['GET', 'HEAD'],
            'admin.education.courses.create' => ['GET', 'HEAD'],
            'admin.education.courses.store' => ['POST'],
            'admin.education.courses.show' => ['GET', 'HEAD'],
            'admin.education.courses.edit' => ['GET', 'HEAD'],
            'admin.education.courses.update' => ['PATCH'],
            'admin.education.courses.publication' => ['PATCH'],
            'admin.education.courses.destroy' => ['DELETE'],
        ];

        foreach ($expected as $routeName => $methods) {
            $route = $this->namedRoute($routeName);

            $this->assertContains(
                'admin.auth',
                $route->gatherMiddleware(),
                "Route [{$routeName}] must use admin.auth middleware."
            );

            $this->assertSame(
                $methods,
                $route->methods(),
                "Unexpected HTTP method contract for [{$routeName}]."
            );
        }
    }

    public function test_course_member_routes_require_numeric_course_ids(): void
    {
        $routeNames = [
            'admin.education.courses.show',
            'admin.education.courses.edit',
            'admin.education.courses.update',
            'admin.education.courses.publication',
            'admin.education.courses.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route = $this->namedRoute($routeName);

            $pattern = $route->wheres['course'] ?? null;

            $this->assertIsString(
                $pattern,
                "Route [{$routeName}] must constrain {course}."
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
    }

    public function test_admin_without_education_view_cannot_open_course_index(): void
    {
        $admin = $this->createAdminWithPermissions();

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(
                route('admin.education.courses.index')
            );

        $response->assertForbidden();
    }

    public function test_admin_with_education_view_can_open_course_index(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.view',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->get(
                route('admin.education.courses.index')
            );

        $response
            ->assertOk()
            ->assertViewIs(
                'admin.education.courses.index'
            );
    }

    public function test_education_view_does_not_grant_course_creation(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.view',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route('admin.education.courses.store'),
                $this->validCoursePayload()
            );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'courses',
            0
        );
    }

    public function test_education_publish_does_not_grant_normal_course_update(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.publish',
        ]);

        $course = $this->createCourse();

        $response = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.update',
                    $course
                ),
                [
                    'title' => 'Unauthorized Update',
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            'Security Test Course',
            $course->fresh()->title
        );
    }

    public function test_education_update_does_not_grant_publication_permission(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.update',
        ]);

        $course = $this->createCourse();

        $response = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.publication',
                    $course
                ),
                [
                    'action' => 'PUBLISH',
                ]
            );

        $response->assertForbidden();

        $this->assertSame(
            Course::STATUS_DRAFT,
            $course->fresh()->status
        );

        $this->assertNull(
            $course->fresh()->published_at
        );
    }

    public function test_education_view_does_not_grant_delete_permission(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.view',
        ]);

        $course = $this->createCourse();

        $response = $this
            ->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.education.courses.destroy',
                    $course
                )
            );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'courses',
            [
                'id' => $course->id,
                'deleted_at' => null,
            ]
        );
    }

    public function test_create_request_rejects_client_controlled_publication_fields(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        $payload = $this->validCoursePayload();

        $payload['status'] =
            Course::STATUS_PUBLISHED;

        $payload['published_at'] =
            now()->toDateTimeString();

        $response = $this
            ->actingAs($admin, 'admin')
            ->from(
                route(
                    'admin.education.courses.create'
                )
            )
            ->post(
                route(
                    'admin.education.courses.store'
                ),
                $payload
            );

        $response
            ->assertRedirect(
                route(
                    'admin.education.courses.create'
                )
            )
            ->assertSessionHasErrors([
                'status',
                'published_at',
            ]);

        $this->assertDatabaseCount(
            'courses',
            0
        );
    }

    public function test_update_request_rejects_publication_state_bypass(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.update',
        ]);

        $course = $this->createCourse();

        $response = $this
            ->actingAs($admin, 'admin')
            ->from(
                route(
                    'admin.education.courses.edit',
                    $course
                )
            )
            ->patch(
                route(
                    'admin.education.courses.update',
                    $course
                ),
                [
                    'title' =>
                        'Attempted Publication Bypass',

                    'status' =>
                        Course::STATUS_PUBLISHED,

                    'published_at' =>
                        now()->toDateTimeString(),
                ]
            );

        $response
            ->assertRedirect(
                route(
                    'admin.education.courses.edit',
                    $course
                )
            )
            ->assertSessionHasErrors([
                'status',
                'published_at',
            ]);

        $fresh = $course->fresh();

        $this->assertSame(
            'Security Test Course',
            $fresh->title
        );

        $this->assertSame(
            Course::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertNull(
            $fresh->published_at
        );
    }

    private function namedRoute(
        string $name
    ): LaravelRoute {
        $route = Route::getRoutes()
            ->getByName($name);

        if (! $route instanceof LaravelRoute) {
            $this->fail(
                "Route [{$name}] was not registered."
            );
        }

        return $route;
    }

    private function createAdminWithPermissions(
        array $permissions = []
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),

                'name' =>
                    'Education Security Admin',

                'email' =>
                    'education-security-'
                    .Str::lower(
                        Str::random(12)
                    )
                    .'@tradex.test',

                'email_verified_at' =>
                    now(),

                'password' =>
                    'TestingPassword123!',

                'is_active' =>
                    true,

                'failed_login_attempts' =>
                    0,

                'locked_until' =>
                    null,
            ]);

        if ($permissions === []) {
            return $admin;
        }

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'EDU_SEC_'
                    .strtoupper(
                        Str::random(10)
                    ),

                'name' =>
                    'Education Security Test Role',

                'description' =>
                    'ADMIN-03 automated security test role.',

                'is_system' =>
                    false,

                'is_active' =>
                    true,
            ]);

        $admin->roles()
            ->attach(
                $role->id
            );

        foreach ($permissions as $permissionCode) {
            $permission = AdminPermission::query()
                ->create([
                    'code' =>
                        $permissionCode,

                    'name' =>
                        $permissionCode,

                    'module' =>
                        'EDUCATION',

                    'description' =>
                        'ADMIN-03 automated security permission.',
                ]);

            $role->permissions()
                ->attach(
                    $permission->id
                );
        }

        return $admin->fresh();
    }

    private function createCourse(): Course
    {
        return Course::query()
            ->create([
                'code' =>
                    'SEC-COURSE-01',

                'slug' =>
                    'security-test-course',

                'title' =>
                    'Security Test Course',

                'short_description' =>
                    'Course fixture for ADMIN-03 security tests.',

                'description' =>
                    'Security test course description.',

                'thumbnail_path' =>
                    null,

                'language_code' =>
                    'en',

                'access_level' =>
                    Course::ACCESS_PREMIUM,

                'status' =>
                    Course::STATUS_DRAFT,

                'published_at' =>
                    null,

                'sort_order' =>
                    1,

                'is_featured' =>
                    false,
            ]);
    }

    private function validCoursePayload(): array
    {
        return [
            'code' =>
                'SEC-CREATE-01',

            'slug' =>
                'security-create-course',

            'title' =>
                'Security Create Course',

            'short_description' =>
                'Valid create payload.',

            'description' =>
                'Valid course body.',

            'thumbnail_path' =>
                'education/courses/security/thumbnail.webp',

            'language_code' =>
                'en',

            'access_level' =>
                Course::ACCESS_PREMIUM,

            'sort_order' =>
                1,

            'is_featured' =>
                false,
        ];
    }
}