<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationCatalogTest extends TestCase
{
    use CreatesEducationContent;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_published_course_module_and_lesson_are_available_to_authenticated_user(): void
    {
        [
            $course,
            $module,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_FREE
        );

        $course->forceFill([
            'thumbnail_path' => 'education/courses/market-structure/thumbnail.webp',
        ])->save();
        $module->forceFill([
            'thumbnail_path' => 'education/modules/market-structure/module.webp',
        ])->save();
        $lesson->forceFill([
            'thumbnail_path' => 'education/lessons/market-structure/lesson.webp',
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
            )
            ->assertJsonPath(
                'data.items.0.thumbnail_path',
                'education/courses/market-structure/thumbnail.webp'
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
            )
            ->assertJsonPath(
                'data.modules.0.thumbnail_path',
                'education/modules/market-structure/module.webp'
            )
            ->assertJsonPath(
                'data.course.thumbnail_path',
                'education/courses/market-structure/thumbnail.webp'
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
            ->assertJsonPath(
                'data.lesson.thumbnail_path',
                'education/lessons/market-structure/lesson.webp'
            )
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
            'status' => Course::STATUS_DRAFT,
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
