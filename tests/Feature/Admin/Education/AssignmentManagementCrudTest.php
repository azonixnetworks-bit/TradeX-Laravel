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
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_assignment_as_draft_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.assignments.store', compact('course', 'module', 'lesson')),
            [
                'title' => 'Market Structure Assignment',
                'heading' => 'Mark the swing structure',
                'description' => 'Complete the chart exercise.',
                'reference_image_path' => 'assignments/reference/structure.png',
                'submission_method' => 'whatsapp',
                'submission_instructions' => 'Send the completed work through the approved TradeX WhatsApp contact.',
                'is_required' => 1,
                'sort_order' => 1,
            ]
        );

        $assignment = Assignment::query()->where('lesson_id', $lesson->id)->firstOrFail();

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        );

        $this->assertSame(Assignment::SUBMISSION_WHATSAPP, $assignment->submission_method);
        $this->assertSame(Assignment::STATUS_DRAFT, $assignment->status);
        $this->assertTrue($assignment->is_required);
        $this->assertNull($assignment->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'ASSIGNMENT_CREATED',
            'target_type' => 'ASSIGNMENT',
            'target_id' => $assignment->id,
        ]);
    }

    public function test_authorized_admin_can_update_assignment_policy_metadata(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $assignment = $this->createAssignment($lesson);

        $response = $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.assignments.update', compact('course', 'module', 'lesson', 'assignment')),
            [
                'title' => 'Updated Assignment',
                'submission_method' => Assignment::SUBMISSION_NONE,
                'submission_instructions' => null,
                'is_required' => 0,
                'sort_order' => 5,
            ]
        );

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        );

        $assignment->refresh();
        $this->assertSame('Updated Assignment', $assignment->title);
        $this->assertSame(Assignment::SUBMISSION_NONE, $assignment->submission_method);
        $this->assertFalse($assignment->is_required);
        $this->assertSame(Assignment::STATUS_DRAFT, $assignment->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ASSIGNMENT_UPDATED',
            'target_id' => $assignment->id,
        ]);
    }

    public function test_assignment_can_publish_and_unpublish_with_audit_history(): void
    {
        $admin = $this->createAdminWithPermissions(['education.publish']);
        [$course, $module, $lesson] = $this->createHierarchy();
        $assignment = $this->createAssignment($lesson);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.assignments.publication', compact('course', 'module', 'lesson', 'assignment')),
            ['action' => 'PUBLISH', 'reason' => 'Instructions approved']
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        );

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_PUBLISHED, $assignment->status);
        $this->assertNotNull($assignment->published_at);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.assignments.publication', compact('course', 'module', 'lesson', 'assignment')),
            ['action' => 'UNPUBLISH']
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        );

        $assignment->refresh();
        $this->assertSame(Assignment::STATUS_DRAFT, $assignment->status);
        $this->assertNull($assignment->published_at);

        $this->assertDatabaseHas('audit_logs', ['action' => 'ASSIGNMENT_PUBLISHED', 'target_id' => $assignment->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ASSIGNMENT_UNPUBLISHED', 'target_id' => $assignment->id]);
    }

    public function test_published_assignment_cannot_delete_but_draft_assignment_can_soft_delete(): void
    {
        $admin = $this->createAdminWithPermissions(['education.delete']);
        [$course, $module, $lesson] = $this->createHierarchy();

        $published = $this->createAssignment($lesson, [
            'status' => Assignment::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.assignments.destroy', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lesson,
                'assignment' => $published,
            ])
        )->assertStatus(422);

        $draft = $this->createAssignment($lesson);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.assignments.destroy', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lesson,
                'assignment' => $draft,
            ])
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.assignments.index', compact('course', 'module', 'lesson'))
        );

        $this->assertSoftDeleted('assignments', ['id' => $draft->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ASSIGNMENT_DELETED',
            'target_id' => $draft->id,
        ]);
    }

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Assignment CRUD Admin',
            'email' => 'assignment-crud-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ASSIGN_CRUD_'.strtoupper(Str::random(8)),
            'name' => 'Assignment CRUD Role',
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
            'code' => 'ASSIGN-C-'.strtoupper(Str::random(5)),
            'slug' => 'assign-course-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Parent Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'ASSIGN-M-'.strtoupper(Str::random(5)),
            'slug' => 'assign-module-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Parent Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'ASSIGN-L-'.strtoupper(Str::random(5)),
            'slug' => 'assign-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Parent Lesson',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module, $lesson];
    }

    private function createAssignment(Lesson $lesson, array $overrides = []): Assignment
    {
        return Assignment::query()->create(array_merge([
            'lesson_id' => $lesson->id,
            'title' => 'Assignment Fixture',
            'heading' => 'Fixture Heading',
            'description' => 'Assignment fixture description.',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' => 'Send externally.',
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'published_at' => null,
            'sort_order' => 1,
        ], $overrides));
    }
}
