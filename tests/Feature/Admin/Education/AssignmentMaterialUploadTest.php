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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentMaterialUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_create_assignment_with_protected_material(): void
    {
        Storage::fake(Assignment::MATERIAL_DISK);

        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')->post(
            route(
                'admin.education.courses.modules.lessons.assignments.store',
                compact('course', 'module', 'lesson')
            ),
            [
                'title' => 'Protected Question Paper',
                'description' => 'Complete the attached assignment.',
                'material_file' => UploadedFile::fake()->create(
                    'question-paper.pdf',
                    320,
                    'application/pdf'
                ),
                'submission_method' => Assignment::SUBMISSION_WHATSAPP,
                'submission_instructions' => 'Send the answer through WhatsApp.',
                'is_required' => 1,
                'sort_order' => 1,
            ]
        );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.lessons.assignments.index',
                compact('course', 'module', 'lesson')
            )
        );

        $assignment = Assignment::query()
            ->where('title', 'Protected Question Paper')
            ->firstOrFail();

        $this->assertTrue($assignment->hasMaterial());
        $this->assertSame(
            Assignment::MATERIAL_DISK,
            $assignment->material_disk
        );
        $this->assertSame(
            'question-paper.pdf',
            $assignment->material_original_name
        );
        $this->assertNotNull($assignment->material_sha256);
        $this->assertSame(64, strlen($assignment->material_sha256));
        $this->assertNotNull($assignment->material_uploaded_at);

        Storage::disk(
            Assignment::MATERIAL_DISK
        )->assertExists(
            $assignment->material_path
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ASSIGNMENT_CREATED',
            'target_id' => $assignment->id,
        ]);
    }

    public function test_authorized_admin_can_replace_assignment_material(): void
    {
        Storage::fake(Assignment::MATERIAL_DISK);

        $admin = $this->createAdminWithPermissions([
            'education.update',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $assignment = $this->createAssignmentWithMaterial(
            $lesson,
            'old.pdf',
            'old material'
        );

        $oldPath = $assignment->material_path;

        $response = $this->actingAs($admin, 'admin')->patch(
            route(
                'admin.education.courses.modules.lessons.assignments.update',
                compact('course', 'module', 'lesson', 'assignment')
            ),
            [
                'title' => $assignment->title,
                'submission_method' => $assignment->submission_method,
                'is_required' => 1,
                'sort_order' => 1,
                'material_file' => UploadedFile::fake()->create(
                    'replacement.pdf',
                    200,
                    'application/pdf'
                ),
            ]
        );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.lessons.assignments.index',
                compact('course', 'module', 'lesson')
            )
        );

        $assignment->refresh();

        $this->assertSame(
            'replacement.pdf',
            $assignment->material_original_name
        );
        $this->assertNotSame(
            $oldPath,
            $assignment->material_path
        );

        Storage::disk(
            Assignment::MATERIAL_DISK
        )->assertMissing($oldPath);

        Storage::disk(
            Assignment::MATERIAL_DISK
        )->assertExists(
            $assignment->material_path
        );
    }

    public function test_authorized_admin_can_remove_assignment_material(): void
    {
        Storage::fake(Assignment::MATERIAL_DISK);

        $admin = $this->createAdminWithPermissions([
            'education.update',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $assignment = $this->createAssignmentWithMaterial(
            $lesson,
            'remove.pdf',
            'remove material'
        );

        $oldPath = $assignment->material_path;

        $response = $this->actingAs($admin, 'admin')->patch(
            route(
                'admin.education.courses.modules.lessons.assignments.update',
                compact('course', 'module', 'lesson', 'assignment')
            ),
            [
                'title' => $assignment->title,
                'submission_method' => $assignment->submission_method,
                'is_required' => 1,
                'sort_order' => 1,
                'remove_material' => 1,
            ]
        );

        $response->assertRedirect(
            route(
                'admin.education.courses.modules.lessons.assignments.index',
                compact('course', 'module', 'lesson')
            )
        );

        $assignment->refresh();

        $this->assertFalse($assignment->hasMaterial());
        $this->assertNull($assignment->material_path);
        $this->assertNull($assignment->material_sha256);

        Storage::disk(
            Assignment::MATERIAL_DISK
        )->assertMissing($oldPath);
    }

    public function test_executable_admin_material_is_rejected(): void
    {
        Storage::fake(Assignment::MATERIAL_DISK);

        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')
            ->from(
                route(
                    'admin.education.courses.modules.lessons.assignments.create',
                    compact('course', 'module', 'lesson')
                )
            )
            ->post(
                route(
                    'admin.education.courses.modules.lessons.assignments.store',
                    compact('course', 'module', 'lesson')
                ),
                [
                    'title' => 'Blocked Material',
                    'material_file' => UploadedFile::fake()->create(
                        'malware.php',
                        20,
                        'application/x-php'
                    ),
                    'submission_method' => Assignment::SUBMISSION_WHATSAPP,
                    'is_required' => 1,
                    'sort_order' => 1,
                ]
            );

        $response->assertSessionHasErrors([
            'material_file',
        ]);

        $this->assertDatabaseCount(
            'assignments',
            0
        );
    }

    public function test_student_answer_upload_fields_remain_prohibited(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this->actingAs($admin, 'admin')
            ->from(
                route(
                    'admin.education.courses.modules.lessons.assignments.create',
                    compact('course', 'module', 'lesson')
                )
            )
            ->post(
                route(
                    'admin.education.courses.modules.lessons.assignments.store',
                    compact('course', 'module', 'lesson')
                ),
                [
                    'title' => 'Student Upload Attempt',
                    'submission_method' => Assignment::SUBMISSION_WHATSAPP,
                    'submission_file' => UploadedFile::fake()->create(
                        'student-answer.pdf',
                        100,
                        'application/pdf'
                    ),
                    'is_required' => 1,
                    'sort_order' => 1,
                ]
            );

        $response->assertSessionHasErrors([
            'submission_file',
        ]);

        $this->assertDatabaseCount(
            'assignments',
            0
        );
    }

    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Assignment Material Test Admin',
            'email' =>
                'assignment-material-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' =>
                'ASSIGN_MATERIAL_'
                .strtoupper(Str::random(8)),
            'name' => 'Assignment Material Role',
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

            $role->permissions()->attach(
                $permission->id
            );
        }

        return $admin->fresh();
    }

    private function createHierarchy(): array
    {
        $course = Course::query()->create([
            'code' =>
                'AM-C-'.strtoupper(Str::random(5)),
            'slug' =>
                'am-course-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Material Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' =>
                'AM-M-'.strtoupper(Str::random(5)),
            'slug' =>
                'am-module-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Material Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' =>
                'AM-L-'.strtoupper(Str::random(5)),
            'slug' =>
                'am-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Assignment Material Lesson',
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

    private function createAssignmentWithMaterial(
        Lesson $lesson,
        string $originalName,
        string $contents
    ): Assignment {
        $uuid = Str::uuid()->toString();

        $path =
            'education/assignments/'
            .$uuid
            .'/materials/'
            .Str::uuid()
            .'.pdf';

        Storage::disk(
            Assignment::MATERIAL_DISK
        )->put(
            $path,
            $contents
        );

        return Assignment::query()->create([
            'uuid' => $uuid,
            'lesson_id' => $lesson->id,
            'title' => 'Assignment Material Fixture',
            'submission_method' =>
                Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' =>
                'Send externally.',
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'sort_order' => 1,
            'material_disk' =>
                Assignment::MATERIAL_DISK,
            'material_path' => $path,
            'material_original_name' => $originalName,
            'material_mime_type' => 'application/pdf',
            'material_size_bytes' =>
                strlen($contents),
            'material_sha256' =>
                hash('sha256', $contents),
            'material_uploaded_at' => now(),
        ]);
    }
}
