<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationCatalogTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;

    public function test_published_course_module_and_lesson_are_available_to_authenticated_user(): void
    {
        [
            $course,
            $module,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_FREE
        );

        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/courses'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.items.0.uuid',
                $course->uuid
            )
            ->assertJsonPath(
                'data.items.0.can_access',
                true
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/courses/'
                .$course->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.modules.0.uuid',
                $module->uuid
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
            )
            ->assertOk()
            ->assertJsonMissing(
                [
                    'hls_master_path',
                ]
            )
            ->assertJsonMissing(
                [
                    'source_path',
                ]
            );
    }

    public function test_draft_course_is_not_exposed(): void
    {
        [
            $course,
        ] = $this->createEducationTree();

        $course->forceFill([
            'status' =>
                Course::STATUS_DRAFT,
        ])->save();

        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/courses/'
                .$course->uuid
            )
            ->assertNotFound();
    }
}
