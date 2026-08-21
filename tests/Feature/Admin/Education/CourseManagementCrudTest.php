<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourseManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_course_as_draft_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.education.courses.store'
                ),
                [
                    'code' =>
                        'SMC-PRO-01',

                    'slug' =>
                        'smc-professional-trading',

                    'title' =>
                        'SMC Professional Trading',

                    'short_description' =>
                        'Professional TradeX education course.',

                    'description' =>
                        'Complete trading education programme.',

                    'thumbnail_path' =>
                        'education/courses/smc-pro/thumbnail.webp',

                    'language_code' =>
                        'ta',

                    'access_level' =>
                        Course::ACCESS_PREMIUM,

                    'sort_order' =>
                        1,

                    'is_featured' =>
                        true,
                ]
            );

        $course = Course::query()
            ->where(
                'code',
                'SMC-PRO-01'
            )
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'admin.education.courses.show',
                $course
            )
        );

        $this->assertSame(
            Course::STATUS_DRAFT,
            $course->status
        );

        $this->assertNull(
            $course->published_at
        );

        $this->assertTrue(
            $course->is_featured
        );

        $this->assertSame(
            $admin->id,
            $course->created_by_admin_id
        );

        $this->assertSame(
            $admin->id,
            $course->updated_by_admin_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'ADMIN',

                'actor_id' =>
                    $admin->id,

                'action' =>
                    'COURSE_CREATED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );
    }

    public function test_authorized_admin_can_update_course_and_clear_featured_flag(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.update',
        ]);

        $course = $this->createCourse([
            'is_featured' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.update',
                    $course
                ),
                [
                    'title' =>
                        'Updated TradeX Course',

                    'short_description' =>
                        'Updated short description.',

                    'is_featured' =>
                        0,
                ]
            );

        $response->assertRedirect(
            route(
                'admin.education.courses.show',
                $course
            )
        );

        $fresh = $course->fresh();

        $this->assertSame(
            'Updated TradeX Course',
            $fresh->title
        );

        $this->assertSame(
            'Updated short description.',
            $fresh->short_description
        );

        $this->assertFalse(
            $fresh->is_featured
        );

        $this->assertSame(
            Course::STATUS_DRAFT,
            $fresh->status
        );

        $this->assertNull(
            $fresh->published_at
        );

        $this->assertSame(
            $admin->id,
            $fresh->updated_by_admin_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'COURSE_UPDATED',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );
    }

    public function test_authorized_admin_can_publish_and_unpublish_course(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.publish',
        ]);

        $course = $this->createCourse();

        $publishResponse = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.publication',
                    $course
                ),
                [
                    'action' =>
                        'PUBLISH',

                    'reason' =>
                        'Curriculum review completed.',
                ]
            );

        $publishResponse->assertRedirect(
            route(
                'admin.education.courses.show',
                $course
            )
        );

        $published = $course->fresh();

        $this->assertSame(
            Course::STATUS_PUBLISHED,
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
                    'COURSE_PUBLISHED',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );

        $unpublishResponse = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.publication',
                    $course
                ),
                [
                    'action' =>
                        'UNPUBLISH',

                    'reason' =>
                        'Returning course to draft for revision.',
                ]
            );

        $unpublishResponse->assertRedirect(
            route(
                'admin.education.courses.show',
                $course
            )
        );

        $draft = $course->fresh();

        $this->assertSame(
            Course::STATUS_DRAFT,
            $draft->status
        );

        $this->assertNull(
            $draft->published_at
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'COURSE_UNPUBLISHED',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );
    }

    public function test_published_course_cannot_be_deleted_until_unpublished(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.delete',
        ]);

        $course = $this->createCourse([
            'status' =>
                Course::STATUS_PUBLISHED,

            'published_at' =>
                now(),
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.education.courses.destroy',
                    $course
                )
            );

        $response->assertStatus(
            422
        );

        $this->assertDatabaseHas(
            'courses',
            [
                'id' =>
                    $course->id,

                'status' =>
                    Course::STATUS_PUBLISHED,

                'deleted_at' =>
                    null,
            ]
        );
    }

    public function test_authorized_admin_can_soft_delete_unpublished_course_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.delete',
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

        $response->assertRedirect(
            route(
                'admin.education.courses.index'
            )
        );

        $this->assertSoftDeleted(
            'courses',
            [
                'id' =>
                    $course->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_id' =>
                    $admin->id,

                'action' =>
                    'COURSE_DELETED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );
    }

    public function test_invalid_publication_state_transition_is_rejected(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.publish',
        ]);

        $course = $this->createCourse([
            'status' =>
                Course::STATUS_PUBLISHED,

            'published_at' =>
                now(),
        ]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.education.courses.publication',
                    $course
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
            Course::STATUS_PUBLISHED,
            $course->fresh()->status
        );

        $this->assertDatabaseMissing(
            'audit_logs',
            [
                'action' =>
                    'COURSE_PUBLISHED',

                'target_type' =>
                    'COURSE',

                'target_id' =>
                    $course->id,
            ]
        );
    }

    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),

                'name' =>
                    'Education CRUD Admin',

                'email' =>
                    'education-crud-'
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

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'EDU_CRUD_'
                    .strtoupper(
                        Str::random(10)
                    ),

                'name' =>
                    'Education CRUD Test Role',

                'description' =>
                    'ADMIN-03 automated CRUD test role.',

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
                        'ADMIN-03 automated CRUD permission.',
                ]);

            $role->permissions()
                ->attach(
                    $permission->id
                );
        }

        return $admin->fresh();
    }

    private function createCourse(
        array $overrides = []
    ): Course {
        return Course::query()
            ->create(
                array_merge(
                    [
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
                            'CRUD Test Course',

                        'short_description' =>
                            'Course fixture for ADMIN-03 CRUD tests.',

                        'description' =>
                            'CRUD test course description.',

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
                    ],
                    $overrides
                )
            );
    }
}