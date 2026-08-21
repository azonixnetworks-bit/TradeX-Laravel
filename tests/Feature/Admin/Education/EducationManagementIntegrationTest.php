<?php

namespace Tests\Feature\Admin\Education;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Lesson;
use App\Models\LessonVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class EducationManagementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin03_complete_education_route_contract_is_registered(): void
    {
        $required = [
            'admin.education.courses.index',
            'admin.education.courses.create',
            'admin.education.courses.store',
            'admin.education.courses.publication',
            'admin.education.courses.edit',
            'admin.education.courses.update',
            'admin.education.courses.destroy',
            'admin.education.courses.show',

            'admin.education.courses.modules.index',
            'admin.education.courses.modules.create',
            'admin.education.courses.modules.store',
            'admin.education.courses.modules.publication',
            'admin.education.courses.modules.edit',
            'admin.education.courses.modules.update',
            'admin.education.courses.modules.destroy',

            'admin.education.courses.modules.lessons.index',
            'admin.education.courses.modules.lessons.create',
            'admin.education.courses.modules.lessons.store',
            'admin.education.courses.modules.lessons.publication',
            'admin.education.courses.modules.lessons.edit',
            'admin.education.courses.modules.lessons.update',
            'admin.education.courses.modules.lessons.destroy',

            'admin.education.courses.modules.lessons.videos.index',
            'admin.education.courses.modules.lessons.videos.create',
            'admin.education.courses.modules.lessons.videos.store',
            'admin.education.courses.modules.lessons.videos.publication',
            'admin.education.courses.modules.lessons.videos.edit',
            'admin.education.courses.modules.lessons.videos.update',
            'admin.education.courses.modules.lessons.videos.destroy',

            'admin.education.courses.modules.lessons.assignments.index',
            'admin.education.courses.modules.lessons.assignments.create',
            'admin.education.courses.modules.lessons.assignments.store',
            'admin.education.courses.modules.lessons.assignments.publication',
            'admin.education.courses.modules.lessons.assignments.edit',
            'admin.education.courses.modules.lessons.assignments.update',
            'admin.education.courses.modules.lessons.assignments.destroy',
        ];

        foreach ($required as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "ADMIN-03 route [{$routeName}] is missing."
            );
        }
    }

    public function test_complete_course_module_lesson_video_assignment_hierarchy_is_relationally_consistent(): void
    {
        $course = Course::query()->create([
            'code' => 'INT-C-'.strtoupper(Str::random(5)),
            'slug' => 'integration-course-'.Str::lower(Str::random(7)),
            'title' => 'Integration Course',
            'language_code' => 'en',
            'access_level' => Course::ACCESS_PREMIUM,
            'status' => Course::STATUS_DRAFT,
            'sort_order' => 1,
            'is_featured' => false,
        ]);

        $module = CourseModule::query()->create([
            'course_id' => $course->id,
            'code' => 'INT-M-'.strtoupper(Str::random(5)),
            'slug' => 'integration-module-'.Str::lower(Str::random(7)),
            'title' => 'Integration Module',
            'status' => CourseModule::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $lesson = Lesson::query()->create([
            'course_module_id' => $module->id,
            'code' => 'INT-L-'.strtoupper(Str::random(5)),
            'slug' => 'integration-lesson-'.Str::lower(Str::random(7)),
            'title' => 'Integration Lesson',
            'is_preview' => true,
            'status' => Lesson::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $video = LessonVideo::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Integration Video',
            'storage_disk' => 'private',
            'source_path' => 'protected/integration/video.mp4',
            'hls_master_path' => 'protected/integration/master.m3u8',
            'processing_status' => LessonVideo::PROCESSING_READY,
            'processed_at' => now(),
            'status' => LessonVideo::STATUS_DRAFT,
            'is_downloadable' => false,
            'sort_order' => 1,
        ]);

        $assignment = Assignment::query()->create([
            'lesson_id' => $lesson->id,
            'title' => 'Integration Assignment',
            'submission_method' => Assignment::SUBMISSION_WHATSAPP,
            'submission_instructions' => 'Send externally through WhatsApp.',
            'is_required' => true,
            'status' => Assignment::STATUS_DRAFT,
            'sort_order' => 1,
        ]);

        $this->assertTrue($course->modules()->whereKey($module->id)->exists());
        $this->assertTrue($module->lessons()->whereKey($lesson->id)->exists());
        $this->assertTrue($lesson->videos()->whereKey($video->id)->exists());
        $this->assertTrue($lesson->assignments()->whereKey($assignment->id)->exists());

        $this->assertSame($course->id, $module->course_id);
        $this->assertSame($module->id, $lesson->course_module_id);
        $this->assertSame($lesson->id, $video->lesson_id);
        $this->assertSame($lesson->id, $assignment->lesson_id);
    }

    public function test_assignment_policy_does_not_require_or_define_in_app_submission_storage(): void
    {
        $this->assertTrue(defined(Assignment::class.'::SUBMISSION_WHATSAPP'));
        $this->assertTrue(defined(Assignment::class.'::SUBMISSION_NONE'));

        $assignment = new Assignment();

        $this->assertNotContains('submission_file', $assignment->getFillable());
        $this->assertNotContains('submission_upload_path', $assignment->getFillable());
        $this->assertContains('submission_method', $assignment->getFillable());
        $this->assertContains('submission_instructions', $assignment->getFillable());
    }
}
