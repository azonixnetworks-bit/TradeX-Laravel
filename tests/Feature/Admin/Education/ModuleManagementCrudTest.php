<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ModuleManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_module_as_draft_with_audit_log(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.create',
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
                    [
                        'code' =>
                            'SMC-M01',

                        'slug' =>
                            'market-structure-foundations',

                        'title' =>
                            'Market Structure Foundations',

                        'short_description' =>
                            'Foundation module for market structure.',

                        'description' =>
                            'Complete module curriculum description.',

                        'sort_order' =>
                            1,
                    ]
                );

        $module =
            CourseModule::query()
                ->where(
                    'course_id',
                    $course->id
                )
                ->where(
                    'code',
                    'SMC-M01'
                )
                ->firstOrFail();

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $course
            )
        );

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $module->status
        );

        $this->assertNull(
            $module->published_at
        );

        $this->assertSame(
            $course->id,
            $module->course_id
        );

        $this->assertSame(
            $admin->id,
            $module->created_by_admin_id
        );

        $this->assertSame(
            $admin->id,
            $module->updated_by_admin_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'ADMIN',

                'actor_id' =>
                    $admin->id,

                'action' =>
                    'MODULE_CREATED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );
    }


    public function test_module_code_and_slug_must_be_unique_inside_same_course(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.create',
            ]);

        $course =
            $this->createCourse();

        $existing =
            $this->createModule(
                $course,
                [
                    'code' =>
                        'DUP-M01',

                    'slug' =>
                        'duplicate-module',
                ]
            );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->from(
                    route(
                        'admin.education.courses.modules.create',
                        $course
                    )
                )
                ->post(
                    route(
                        'admin.education.courses.modules.store',
                        $course
                    ),
                    [
                        'code' =>
                            $existing->code,

                        'slug' =>
                            $existing->slug,

                        'title' =>
                            'Duplicate Attempt',

                        'sort_order' =>
                            2,
                    ]
                );

        $response
            ->assertRedirect(
                route(
                    'admin.education.courses.modules.create',
                    $course
                )
            )
            ->assertSessionHasErrors([
                'code',
                'slug',
            ]);

        $this->assertSame(
            1,
            CourseModule::query()
                ->where(
                    'course_id',
                    $course->id
                )
                ->count()
        );
    }


    public function test_same_module_code_and_slug_can_be_used_in_different_courses(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.create',
            ]);

        $courseA =
            $this->createCourse();

        $courseB =
            $this->createCourse();

        $this->createModule(
            $courseA,
            [
                'code' =>
                    'REUSE-M01',

                'slug' =>
                    'reusable-module',
            ]
        );

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->post(
                    route(
                        'admin.education.courses.modules.store',
                        $courseB
                    ),
                    [
                        'code' =>
                            'REUSE-M01',

                        'slug' =>
                            'reusable-module',

                        'title' =>
                            'Same Identifier Different Course',

                        'sort_order' =>
                            1,
                    ]
                );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $courseB
            )
        );

        $this->assertDatabaseHas(
            'course_modules',
            [
                'course_id' =>
                    $courseB->id,

                'code' =>
                    'REUSE-M01',

                'slug' =>
                    'reusable-module',
            ]
        );
    }


    public function test_authorized_admin_can_update_module_metadata_with_audit_log(): void
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
                        'admin.education.courses.modules.update',
                        [
                            'course' =>
                                $course,

                            'module' =>
                                $module,
                        ]
                    ),
                    [
                        'code' =>
                            $module->code,

                        'slug' =>
                            $module->slug,

                        'title' =>
                            'Updated Module Title',

                        'short_description' =>
                            'Updated module summary.',

                        'sort_order' =>
                            7,
                    ]
                );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $course
            )
        );

        $fresh =
            $module->fresh();

        $this->assertSame(
            'Updated Module Title',
            $fresh->title
        );

        $this->assertSame(
            'Updated module summary.',
            $fresh->short_description
        );

        $this->assertSame(
            7,
            $fresh->sort_order
        );

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertSame(
            $admin->id,
            $fresh->updated_by_admin_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'MODULE_UPDATED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );
    }


    public function test_authorized_admin_can_publish_and_unpublish_module(): void
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

        $publishResponse =
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

                        'reason' =>
                            'Module curriculum approved.',
                    ]
                );

        $publishResponse->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $course
            )
        );

        $published =
            $module->fresh();

        $this->assertSame(
            CourseModule::STATUS_PUBLISHED,
            $published->status
        );

        $this->assertNotNull(
            $published->published_at
        );

        $this->assertSame(
            $admin->id,
            $published->updated_by_admin_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'MODULE_PUBLISHED',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );

        $unpublishResponse =
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
                            'UNPUBLISH',

                        'reason' =>
                            'Module returned to draft for revision.',
                    ]
                );

        $unpublishResponse->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $course
            )
        );

        $draft =
            $module->fresh();

        $this->assertSame(
            CourseModule::STATUS_DRAFT,
            $draft->status
        );

        $this->assertNull(
            $draft->published_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'MODULE_UNPUBLISHED',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );
    }


    public function test_invalid_module_publication_state_transition_is_rejected(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.publish',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course,
                [
                    'status' =>
                        CourseModule::STATUS_PUBLISHED,

                    'published_at' =>
                        now(),
                ]
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

        $response->assertStatus(
            422
        );

        $this->assertSame(
            CourseModule::STATUS_PUBLISHED,
            $module
                ->fresh()
                ->status
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action' =>
                    'MODULE_PUBLISHED',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );
    }


    public function test_published_module_cannot_be_deleted_until_unpublished(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.delete',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course,
                [
                    'status' =>
                        CourseModule::STATUS_PUBLISHED,

                    'published_at' =>
                        now(),
                ]
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

        $response->assertStatus(
            422
        );

        $this->assertDatabaseHas(
            'course_modules',
            [
                'id' =>
                    $module->id,

                'status' =>
                    CourseModule::STATUS_PUBLISHED,

                'deleted_at' =>
                    null,
            ]
        );
    }


    public function test_module_with_lessons_cannot_be_deleted(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.delete',
            ]);

        $course =
            $this->createCourse();

        $module =
            $this->createModule(
                $course
            );

        $lesson =
            $this->createLesson(
                $module
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

        $response->assertStatus(
            422
        );

        $this->assertDatabaseHas(
            'course_modules',
            [
                'id' =>
                    $module->id,

                'deleted_at' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'lessons',
            [
                'id' =>
                    $lesson->id,

                'course_module_id' =>
                    $module->id,

                'deleted_at' =>
                    null,
            ]
        );
    }


    public function test_authorized_admin_can_soft_delete_empty_draft_module_with_audit_log(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'education.delete',
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

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.index',
                $course
            )
        );

        $this->assertSoftDeleted(
            'course_modules',
            [
                'id' =>
                    $module->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $admin->id,

                'action' =>
                    'MODULE_DELETED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'COURSE_MODULE',

                'target_id' =>
                    $module->id,
            ]
        );
    }


    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'Module CRUD Admin',

                    'email' =>
                        'module-crud-'
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

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'MOD_CRUD_'
                        .strtoupper(
                            Str::random(10)
                        ),

                    'name' =>
                        'Module CRUD Test Role',

                    'description' =>
                        'ADMIN-03 module CRUD test role.',

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
                            'ADMIN-03 module CRUD test permission.',
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
        return Course::query()
            ->create([
                'code' =>
                    'CRUD-COURSE-'
                    .strtoupper(
                        Str::random(6)
                    ),

                'slug' =>
                    'crud-course-'
                    .Str::lower(
                        Str::random(8)
                    ),

                'title' =>
                    'Module CRUD Parent Course',

                'short_description' =>
                    'Parent course for module CRUD tests.',

                'description' =>
                    'Module CRUD parent course description.',

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
        Course $course,
        array $overrides = []
    ): CourseModule {
        return CourseModule::query()
            ->create(
                array_merge(
                    [
                        'course_id' =>
                            $course->id,

                        'code' =>
                            'CRUD-MOD-'
                            .strtoupper(
                                Str::random(6)
                            ),

                        'slug' =>
                            'crud-module-'
                            .Str::lower(
                                Str::random(8)
                            ),

                        'title' =>
                            'Module CRUD Test',

                        'short_description' =>
                            'Module fixture for CRUD tests.',

                        'description' =>
                            'Module CRUD fixture description.',

                        'status' =>
                            CourseModule::STATUS_DRAFT,

                        'published_at' =>
                            null,

                        'sort_order' =>
                            1,
                    ],
                    $overrides
                )
            );
    }


    private function createLesson(
        CourseModule $module
    ): Lesson {
        return Lesson::query()
            ->create([
                'course_module_id' =>
                    $module->id,

                'code' =>
                    'LESSON-'
                    .strtoupper(
                        Str::random(6)
                    ),

                'slug' =>
                    'lesson-'
                    .Str::lower(
                        Str::random(8)
                    ),

                'title' =>
                    'Deletion Guard Lesson',

                'heading' =>
                    'Lesson prevents parent deletion',

                'short_description' =>
                    'Lesson fixture for module delete guard.',

                'description' =>
                    'Lesson fixture description.',

                'is_preview' =>
                    false,

                'status' =>
                    Lesson::STATUS_DRAFT,

                'published_at' =>
                    null,

                'sort_order' =>
                    1,
            ]);
    }
}