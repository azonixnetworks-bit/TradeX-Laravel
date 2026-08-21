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

class EducationManagementHardeningTest extends TestCase
{
    use RefreshDatabase;


    public function test_private_education_media_disks_are_configured_and_public_disk_is_rejected(): void
    {
        $this->assertSame(
            'local',
            config('filesystems.disks.private.driver')
        );

        $this->assertSame(
            storage_path('app/private'),
            config('filesystems.disks.private.root')
        );

        $this->assertSame(
            false,
            config('filesystems.disks.private.serve')
        );

        $this->assertSame(
            's3',
            config('filesystems.disks.s3-private.driver')
        );

        $this->assertSame(
            'private',
            config('filesystems.disks.s3-private.visibility')
        );

        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $response = $this
            ->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.education.courses.modules.lessons.videos.store',
                    compact('course', 'module', 'lesson')
                ),
                array_merge(
                    $this->validVideoPayload(),
                    [
                        'storage_disk' => 'public',
                    ]
                )
            );

        $response->assertSessionHasErrors([
            'storage_disk',
        ]);

        $this->assertDatabaseCount(
            'lesson_videos',
            0
        );
    }


    public function test_video_logical_paths_reject_urls_absolute_paths_traversal_and_signed_query_fragments(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $invalidPaths = [
            'https://cdn.example.test/video.mp4?token=secret',
            '//cdn.example.test/video.mp4',
            '/var/private/video.mp4',
            '../secrets/video.mp4',
            'courses/course-1/../secrets/video.mp4',
            'C:\\private\\video.mp4',
            's3://bucket/video.mp4',
            'courses/course-1/video.mp4?X-Amz-Signature=secret',
            'courses/course-1/video.mp4#fragment',
        ];

        foreach ($invalidPaths as $invalidPath) {
            $response = $this
                ->actingAs($admin, 'admin')
                ->post(
                    route(
                        'admin.education.courses.modules.lessons.videos.store',
                        compact('course', 'module', 'lesson')
                    ),
                    array_merge(
                        $this->validVideoPayload(),
                        [
                            'source_path' => $invalidPath,
                        ]
                    )
                );

            $response->assertSessionHasErrors([
                'source_path',
            ]);
        }

        $this->assertDatabaseCount(
            'lesson_videos',
            0
        );
    }


    public function test_assignment_reference_media_rejects_urls_absolute_paths_and_traversal(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $invalidPaths = [
            'https://cdn.example.test/assignment.png?token=secret',
            '//cdn.example.test/assignment.png',
            '/var/private/assignment.png',
            '../private/assignment.png',
            'assignments/../private/assignment.png',
            'C:\\private\\assignment.png',
            'assignments/reference.png?token=secret',
            'assignments/reference.png#fragment',
        ];

        foreach ($invalidPaths as $invalidPath) {
            $response = $this
                ->actingAs($admin, 'admin')
                ->post(
                    route(
                        'admin.education.courses.modules.lessons.assignments.store',
                        compact('course', 'module', 'lesson')
                    ),
                    array_merge(
                        $this->validAssignmentPayload(),
                        [
                            'reference_image_path' => $invalidPath,
                        ]
                    )
                );

            $response->assertSessionHasErrors([
                'reference_image_path',
            ]);
        }

        $this->assertDatabaseCount(
            'assignments',
            0
        );
    }


    public function test_admin03_hierarchy_navigation_is_visible_to_view_authorized_admin(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.view',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $moduleResponse = $this
            ->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.education.courses.modules.index',
                    $course
                )
            );

        $moduleResponse
            ->assertOk()
            ->assertSee('Manage Lessons');

        $lessonResponse = $this
            ->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.education.courses.modules.lessons.index',
                    [
                        'course' => $course,
                        'module' => $module,
                    ]
                )
            );

        $lessonResponse
            ->assertOk()
            ->assertSee('Manage Videos')
            ->assertSee('Manage Assignments');
    }


    public function test_video_and_assignment_create_edit_views_do_not_render_malformed_blade_comment_markers(): void
    {
        $admin = $this->createAdminWithPermissions([
            'education.create',
            'education.update',
        ]);

        [$course, $module, $lesson] = $this->createHierarchy();

        $video = LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Hardening Video',
            'storage_disk' => 'private',
            'source_path' => 'courses/hardening/source.mp4',
            'processing_status' => LessonVideo::PROCESSING_PENDING,
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);

        $assignment = Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Hardening Assignment',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' => 'Send through the approved WhatsApp contact.',
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $responses = [
            $this
                ->actingAs($admin, 'admin')
                ->get(
                    route(
                        'admin.education.courses.modules.lessons.videos.create',
                        compact('course', 'module', 'lesson')
                    )
                ),

            $this
                ->actingAs($admin, 'admin')
                ->get(
                    route(
                        'admin.education.courses.modules.lessons.videos.edit',
                        compact('course', 'module', 'lesson', 'video')
                    )
                ),

            $this
                ->actingAs($admin, 'admin')
                ->get(
                    route(
                        'admin.education.courses.modules.lessons.assignments.create',
                        compact('course', 'module', 'lesson')
                    )
                ),

            $this
                ->actingAs($admin, 'admin')
                ->get(
                    route(
                        'admin.education.courses.modules.lessons.assignments.edit',
                        compact('course', 'module', 'lesson', 'assignment')
                    )
                ),
        ];

        foreach ($responses as $response) {
            $response
                ->assertOk()
                ->assertDontSee(
                    '{-- TradeX Admin',
                    false
                );
        }
    }


    private function createAdminWithPermissions(
        array $permissions
    ): AdminUser {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Education Hardening Admin',
            'email' => 'edu-hardening-'
                .Str::lower(Str::random(12))
                .'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'EDU_HARD_'
                .strtoupper(Str::random(10)),
            'name' => 'Education Hardening Role',
            'description' => 'ADMIN-03 final hardening test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin
            ->roles()
            ->attach($role->id);

        foreach ($permissions as $permissionCode) {
            $permission = AdminPermission::query()->create([
                'code' => $permissionCode,
                'name' => $permissionCode,
                'module' => 'EDUCATION',
                'description' => 'ADMIN-03 hardening permission.',
            ]);

            $role
                ->permissions()
                ->attach($permission->id);
        }

        return $admin->fresh();
    }


    private function createHierarchy(): array
    {
        $course = Course::query()->create([
            'code' => 'HARD-C-'
                .strtoupper(Str::random(5)),
            'slug' => 'hardening-course-'
                .Str::lower(Str::random(7)),
            'title' => 'Hardening Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'HARD-M-'
                .strtoupper(Str::random(5)),
            'slug' => 'hardening-module-'
                .Str::lower(Str::random(7)),
            'title' => 'Hardening Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'HARD-L-'
                .strtoupper(Str::random(5)),
            'slug' => 'hardening-lesson-'
                .Str::lower(Str::random(7)),
            'title' => 'Hardening Lesson',
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


    private function validVideoPayload(): array
    {
        return [
            'title' => 'Hardening Protected Video',
            'storage_disk' => 'private',
            'source_path' => 'courses/hardening/source.mp4',
            'sort_order' => 1,
        ];
    }


    private function validAssignmentPayload(): array
    {
        return [
            'title' => 'Hardening Assignment',
            'reference_image_path' => 'assignments/hardening/reference.png',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' => 'Send externally through WhatsApp.',
            'is_required' => true,
            'sort_order' => 1,
        ];
    }
}
