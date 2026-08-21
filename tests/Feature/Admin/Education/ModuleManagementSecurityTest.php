<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleManagementSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_module_routes_require_admin_auth_and_keep_expected_http_methods(): void
    {
        $expected = [
            'admin.education.courses.modules.index' => [
                'GET',
                'HEAD',
            ],

            'admin.education.courses.modules.create' => [
                'GET',
                'HEAD',
            ],

            'admin.education.courses.modules.store' => [
                'POST',
            ],

            'admin.education.courses.modules.publication' => [
                'PATCH',
            ],

            'admin.education.courses.modules.edit' => [
                'GET',
                'HEAD',
            ],

            'admin.education.courses.modules.update' => [
                'PATCH',
            ],

            'admin.education.courses.modules.destroy' => [
                'DELETE',
            ],
        ];

        foreach (
            $expected
            as $routeName => $methods
        ) {
            $route =
                $this->namedRoute(
                    $routeName
                );

            $this->assertContains(
                'admin.auth',
                $route->gatherMiddleware(),
                "Route [{$routeName}] must use admin.auth middleware."
            );

            $this->assertSame(
                $methods,
                $route->methods(),
                "Unexpected HTTP method contract for route [{$routeName}]."
            );
        }
    }


    public function test_nested_module_member_routes_enforce_scoped_bindings(): void
    {
        $routeNames = [
            'admin.education.courses.modules.publication',
            'admin.education.courses.modules.edit',
            'admin.education.courses.modules.update',
            'admin.education.courses.modules.destroy',
        ];

        foreach ($routeNames as $routeName) {
            $route =
                $this->namedRoute(
                    $routeName
                );

            $this->assertTrue(
                $route->enforcesScopedBindings(),
                "Route [{$routeName}] must enforce scoped bindings."
            );
        }
    }


    public function test_module_route_parameters_are_numeric_only(): void
    {
        $courseOnlyRoutes = [
            'admin.education.courses.modules.index',
            'admin.education.courses.modules.create',
            'admin.education.courses.modules.store',
        ];

        foreach ($courseOnlyRoutes as $routeName) {
            $this->assertNumericConstraint(
                $this->namedRoute(
                    $routeName
                ),
                'course',
                $routeName
            );
        }

        $nestedRoutes = [
            'admin.education.courses.modules.publication',
            'admin.education.courses.modules.edit',
            'admin.education.courses.modules.update',
            'admin.education.courses.modules.destroy',
        ];

        foreach ($nestedRoutes as $routeName) {
            $route =
                $this->namedRoute(
                    $routeName
                );

            $this->assertNumericConstraint(
                $route,
                'course',
                $routeName
            );

            $this->assertNumericConstraint(
                $route,
                'module',
                $routeName
            );
        }
    }


    public function test_guest_cannot_open_module_management(): void
    {
        $course =
            $this->createCourse();

        $response =
            $this->get(
                route(
                    'admin.education.courses.modules.index',
                    $course
                )
            );

        $response->assertRedirect(
            route(
                'admin.login'
            )
        );
    }


    public function test_admin_without_education_view_cannot_open_module_index(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $course =
            $this->createCourse();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.education.courses.modules.index',
                        $course
                    )
                );

        $response->assertForbidden();
    }


    public function test_admin_with_education_view_can_open_module_index(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.view',
            ]);

        $course =
            $this->createCourse();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.education.courses.modules.index',
                        $course
                    )
                );

        $response
            ->assertOk()
            ->assertViewIs(
                'admin.education.modules.index'
            );
    }


    public function test_education_view_does_not_grant_module_creation(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.view',
            ]);

        $course =
            $this->createCourse();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->post(
                    route(
                        'admin.education.courses.modules.store',
                        $course
                    ),
                    $this->validModulePayload()
                );

        $response->assertForbidden();

        $this->assertDatabaseCount(
            'course_modules',
            0
        );
    }


    public function test_education_publish_does_not_grant_normal_module_update(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.publish',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course
            );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.education.courses.modules.update',
                        [
                            'course' =>
                                $course,

                            'module' =>
                                $module,
                        ]
                    ),
                    [
                        'title' =>
                            'Unauthorized Module Update',
                    ]
                );

        $response->assertForbidden();

        $this->assertSame(
            'Security Test Module',
            $module
                ->fresh()
                ->title
        );
    }


    public function test_education_update_does_not_grant_module_publication(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.update',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course
            );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.education.courses.modules.publication',
                        [
                            'course' =>
                                $course,

                            'module' =>
                                $module,
                        ]
                    ),
                    [
                        'action' =>
                            'PUBLISH',
                    ]
                );

        $response->assertForbidden();

        $fresh =
            $module->fresh();

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertNull(
            $fresh->published_at
        );
    }


    public function test_education_view_does_not_grant_module_delete(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.view',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course
            );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->delete(
                    route(
                        'admin.education.courses.modules.destroy',
                        [
                            'course' =>
                                $course,

                            'module' =>
                                $module,
                        ]
                    )
                );

        $response->assertForbidden();

        $this->assertDatabaseHas(
            'course_modules',
            [
                'id' =>
                    $module->id,

                'deleted_at' =>
                    null,
            ]
        );
    }


    public function test_store_request_rejects_client_controlled_parent_and_publication_fields(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.create',
            ]);

        $courseA =
            $this->createCourse();

        $courseB =
            $this->createCourse();

        $payload =
            $this->validModulePayload();

        $payload['course_id'] =
            $courseB->id;

        $payload['status'] =
            CourseModule::STATUS_PUBLISHED;

        $payload['published_at'] =
            now()->toDateTimeString();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->from(
                    route(
                        'admin.education.courses.modules.create',
                        $courseA
                    )
                )
                ->post(
                    route(
                        'admin.education.courses.modules.store',
                        $courseA
                    ),
                    $payload
                );

        $response
            ->assertRedirect(
                route(
                    'admin.education.courses.modules.create',
                    $courseA
                )
            )
            ->assertSessionHasErrors([
                'course_id',
                'status',
                'published_at',
            ]);

        $this->assertDatabaseCount(
            'course_modules',
            0
        );
    }


    public function test_update_request_rejects_parent_move_and_publication_bypass(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.update',
            ]);

        $courseA =
            $this->createCourse();

        $courseB =
            $this->createCourse();

        $module =
            $this->createModule(
                $courseA
            );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->from(
                    route(
                        'admin.education.courses.modules.edit',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $module,
                        ]
                    )
                )
                ->patch(
                    route(
                        'admin.education.courses.modules.update',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $module,
                        ]
                    ),
                    [
                        'title' =>
                            'Attempted Protected Update',

                        'course_id' =>
                            $courseB->id,

                        'status' =>
                            CourseModule::STATUS_PUBLISHED,

                        'published_at' =>
                            now()->toDateTimeString(),
                    ]
                );

        $response
            ->assertRedirect(
                route(
                    'admin.education.courses.modules.edit',
                    [
                        'course' =>
                            $courseA,

                        'module' =>
                            $module,
                    ]
                )
            )
            ->assertSessionHasErrors([
                'course_id',
                'status',
                'published_at',
            ]);

        $fresh =
            $module->fresh();

        $this->assertSame(
            $courseA->id,
            $fresh->course_id
        );

        $this->assertSame(
            'Security Test Module',
            $fresh->title
        );

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertNull(
            $fresh->published_at
        );
    }


    public function test_cross_course_module_idor_is_rejected_and_target_remains_unchanged(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.update',
                'education.publish',
                'education.delete',
            ]);

        $courseA =
            $this->createCourse();

        $courseB =
            $this->createCourse();

        $moduleB =
            $this->createModule(
                $courseB
            );

        $editResponse =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.education.courses.modules.edit',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $moduleB,
                        ]
                    )
                );

        $editResponse->assertNotFound();

        $updateResponse =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.education.courses.modules.update',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $moduleB,
                        ]
                    ),
                    [
                        'title' =>
                            'IDOR Update Attempt',
                    ]
                );

        $updateResponse->assertNotFound();

        $publicationResponse =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.education.courses.modules.publication',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $moduleB,
                        ]
                    ),
                    [
                        'action' =>
                            'PUBLISH',
                    ]
                );

        $publicationResponse->assertNotFound();

        $deleteResponse =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->delete(
                    route(
                        'admin.education.courses.modules.destroy',
                        [
                            'course' =>
                                $courseA,

                            'module' =>
                                $moduleB,
                        ]
                    )
                );

        $deleteResponse->assertNotFound();

        $fresh =
            $moduleB->fresh();

        $this->assertSame(
            $courseB->id,
            $fresh->course_id
        );

        $this->assertSame(
            'Security Test Module',
            $fresh->title
        );

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertNull(
            $fresh->deleted_at
        );
    }


    private function namedRoute(
        string $name
    ): LaravelRoute {
        $route =
            Route::getRoutes()
                ->getByName(
                    $name
                );

        if (! $route instanceof LaravelRoute) {
            $this->fail(
                "Route [{$name}] was not registered."
            );
        }

        return $route;
    }


    private function assertNumericConstraint(
        LaravelRoute $route,
        string $parameter,
        string $routeName
    ): void {
        $pattern =
            $route->wheres[
                $parameter
            ] ?? null;

        $this->assertIsString(
            $pattern,
            "Route [{$routeName}] must constrain {{$parameter}}."
        );

        $this->assertSame(
            1,
            preg_match(
                '~^(?:'
                .$pattern
                .')$~',
                '123'
            ),
            "Route [{$routeName}] {{$parameter}} must accept numeric IDs."
        );

        $this->assertSame(
            0,
            preg_match(
                '~^(?:'
                .$pattern
                .')$~',
                'abc'
            ),
            "Route [{$routeName}] {{$parameter}} must reject alphabetic IDs."
        );
    }


    private function createAdminWithPermissions(
        array $permissions = []
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'Module Security Admin',

                    'email' =>
                        'module-security-'
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

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'MOD_SEC_'
                        .strtoupper(
                            Str::random(10)
                        ),

                    'name' =>
                        'Module Security Test Role',

                    'description' =>
                        'ADMIN-03 module security test role.',

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $admin
            ->roles()
            ->attach(
                $role->id
            );

        foreach (
            $permissions
            as $permissionCode
        ) {
            $permission =
                AdminPermission::query()
                    ->create([
                        'code' =>
                            $permissionCode,

                        'name' =>
                            $permissionCode,

                        'module' =>
                            'EDUCATION',

                        'description' =>
                            'ADMIN-03 module security test permission.',
                    ]);

            $role
                ->permissions()
                ->attach(
                    $permission->id
                );
        }

        return $admin->fresh();
    }


    private function createCourse(): Course
    {
        $token =
            Str::lower(
                Str::random(10)
            );

        return Course::query()
            ->create([
                'code' =>
                    'SEC-COURSE-'
                    .strtoupper(
                        Str::random(6)
                    ),

                'slug' =>
                    'security-course-'
                    .$token,

                'title' =>
                    'Security Parent Course',

                'short_description' =>
                    'Parent course for module security tests.',

                'description' =>
                    'Security parent course description.',

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


    private function createModule(
        Course $course
    ): CourseModule {
        return CourseModule::query()
            ->create([
                'course_id' =>
                    $course->id,

                'code' =>
                    'SEC-MOD-'
                    .strtoupper(
                        Str::random(6)
                    ),

                'slug' =>
                    'security-module-'
                    .Str::lower(
                        Str::random(8)
                    ),

                'title' =>
                    'Security Test Module',

                'short_description' =>
                    'Module fixture for security tests.',

                'description' =>
                    'Security module description.',

                'status' =>
                    CourseModule::STATUS_DRAFT,

                'published_at' =>
                    null,

                'sort_order' =>
                    1,
            ]);
    }


    private function validModulePayload(): array
    {
        return [
            'code' =>
                'SEC-M01',

            'slug' =>
                'security-module-create',

            'title' =>
                'Security Module Create',

            'short_description' =>
                'Valid module create payload.',

            'description' =>
                'Valid module description.',

            'sort_order' =>
                1,
        ];
    }
}