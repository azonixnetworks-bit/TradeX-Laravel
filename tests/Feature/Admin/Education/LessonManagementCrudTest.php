<?php

namespace Tests\Feature\Admin\Education;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LessonManagementCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_lesson_as_draft_with_audit_log(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')->post(
            route('admin.education.courses.modules.lessons.store', compact('course', 'module')),
            [
                'code' => ' lesson-01 ',
                'slug' => 'lesson-one',
                'title' => 'Lesson One',
                'heading' => 'Core lesson',
                'short_description' => 'Lesson summary',
                'description' => 'Long lesson description',
                'is_preview' => true,
                'sort_order' => 2,
            ]
        );

        $lesson = Lesson::query()->where('course_module_id', $module->id)->firstOrFail();

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        );

        $this->assertSame('LESSON-01', $lesson->code);
        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status);
        $this->assertTrue($lesson->is_preview);
        $this->assertNull($lesson->published_at);
        $this->assertSame($admin->id, $lesson->created_by_admin_id);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'LESSON_CREATED',
            'module' => 'EDUCATION',
            'target_type' => 'LESSON',
            'target_id' => $lesson->id,
        ]);
    }

    public function test_lesson_code_and_slug_are_unique_inside_same_module(): void
    {
        $admin = $this->createAdminWithPermissions(['education.create']);
        [$course, $module] = $this->createHierarchy();
        $existing = $this->createLesson($module, [
            'code' => 'DUP-LESSON',
            'slug' => 'duplicate-lesson',
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->from(route('admin.education.courses.modules.lessons.create', compact('course', 'module')))
            ->post(
                route('admin.education.courses.modules.lessons.store', compact('course', 'module')),
                [
                    'code' => $existing->code,
                    'slug' => $existing->slug,
                    'title' => 'Duplicate',
                ]
            );

        $response->assertSessionHasErrors(['code', 'slug']);
        $this->assertSame(1, Lesson::query()->where('course_module_id', $module->id)->count());
    }

    public function test_authorized_admin_can_update_lesson_metadata_without_publication_change(): void
    {
        $admin = $this->createAdminWithPermissions(['education.update']);
        [$course, $module] = $this->createHierarchy();
        $lesson = $this->createLesson($module);

        $response = $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.update', compact('course', 'module', 'lesson')),
            [
                'title' => 'Updated Lesson',
                'heading' => 'Updated Heading',
                'is_preview' => 1,
                'sort_order' => 9,
            ]
        );

        $response->assertRedirect(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        );

        $lesson->refresh();
        $this->assertSame('Updated Lesson', $lesson->title);
        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status);
        $this->assertTrue($lesson->is_preview);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LESSON_UPDATED',
            'target_type' => 'LESSON',
            'target_id' => $lesson->id,
        ]);
    }

    public function test_publish_and_unpublish_lesson_are_separate_audited_actions(): void
    {
        $admin = $this->createAdminWithPermissions(['education.publish']);
        [$course, $module] = $this->createHierarchy();
        $lesson = $this->createLesson($module);

        $publish = $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.publication', compact('course', 'module', 'lesson')),
            ['action' => 'PUBLISH', 'reason' => 'Curriculum approved']
        );

        $publish->assertRedirect(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        );

        $lesson->refresh();
        $this->assertSame(Lesson::STATUS_PUBLISHED, $lesson->status);
        $this->assertNotNull($lesson->published_at);

        $this->actingAs($admin, 'admin')->patch(
            route('admin.education.courses.modules.lessons.publication', compact('course', 'module', 'lesson')),
            ['action' => 'UNPUBLISH']
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        );

        $lesson->refresh();
        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status);
        $this->assertNull($lesson->published_at);

        $this->assertDatabaseHas('audit_logs', ['action' => 'LESSON_PUBLISHED', 'target_id' => $lesson->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'LESSON_UNPUBLISHED', 'target_id' => $lesson->id]);
    }

    public function test_lesson_with_video_or_assignment_cannot_be_deleted(): void
    {
        $admin = $this->createAdminWithPermissions(['education.delete']);
        [$course, $module] = $this->createHierarchy();

        $lessonWithVideo = $this->createLesson($module);
        $this->createVideo($lessonWithVideo);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.destroy', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lessonWithVideo,
            ])
        )->assertStatus(422);

        $lessonWithAssignment = $this->createLesson($module);
        $this->createAssignment($lessonWithAssignment);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.destroy', [
                'course' => $course,
                'module' => $module,
                'lesson' => $lessonWithAssignment,
            ])
        )->assertStatus(422);

        $this->assertNull($lessonWithVideo->fresh()->deleted_at);
        $this->assertNull($lessonWithAssignment->fresh()->deleted_at);
    }

    public function test_unpublished_empty_lesson_can_be_soft_deleted(): void
    {
        $admin = $this->createAdminWithPermissions(['education.delete']);
        [$course, $module] = $this->createHierarchy();
        $lesson = $this->createLesson($module);

        $this->actingAs($admin, 'admin')->delete(
            route('admin.education.courses.modules.lessons.destroy', compact('course', 'module', 'lesson'))
        )->assertRedirect(
            route('admin.education.courses.modules.lessons.index', compact('course', 'module'))
        );

        $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LESSON_DELETED',
            'target_type' => 'LESSON',
            'target_id' => $lesson->id,
        ]);
    }

    private function createAdminWithPermissions(array $permissions): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Lesson CRUD Admin',
            'email' => 'lesson-crud-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'LESSON_CRUD_'.strtoupper(Str::random(8)),
            'name' => 'Lesson CRUD Role',
            'description' => 'ADMIN-03 lesson CRUD tests.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($permissions as $code) {
            $permission = AdminPermission::query()->create([
                'code' => $code,
                'name' => $code,
                'module' => 'EDUCATION',
                'description' => 'ADMIN-03 lesson test permission.',
            ]);
            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }

    private function createHierarchy(): array
    {
        $course = Course::query()->create([
            'code' => 'LESSON-COURSE-'.strtoupper(Str::random(5)),
            'slug' => 'lesson-course-'.Str::lower(Str::random(8)),
            'title' => 'Lesson Parent Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'LESSON-MOD-'.strtoupper(Str::random(5)),
            'slug' => 'lesson-module-'.Str::lower(Str::random(8)),
            'title' => 'Lesson Parent Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        return [$course, $module];
    }

    private function createLesson(CourseModule $module, array $overrides = []): Lesson
    {
        return Lesson::query()->create(array_merge([
            'course_module_id' => $module->id,
            'code' => 'LESSON-'.strtoupper(Str::random(6)),
            'slug' => 'lesson-'.Str::lower(Str::random(8)),
            'title' => 'Lesson Fixture',
            'heading' => 'Fixture Heading',
            'short_description' => 'Lesson fixture.',
            'description' => 'Lesson fixture description.',
            'is_preview' => false,
            'status' => Lesson::STATUS_DRAFT,
            'published_at' => null,
            'sort_order' => 1,
        ], $overrides));
    }

    private function createVideo(Lesson $lesson): LessonVideo
    {
        return LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Child Video',
            'storage_disk' => 'private',
            'source_path' => 'tests/video.mp4',
            'processing_status' => LessonVideo::PROCESSING_PENDING,
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);
    }

    private function createAssignment(Lesson $lesson): Assignment
    {
        return Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Child Assignment',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'sort_order' => 1,
        ]);
    }
}
