<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationPremiumGateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;

    public function test_free_user_can_see_premium_outline_but_not_open_locked_lesson(): void
    {
        [
            $course,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            false
        );

        $user =
            $this->createApiUser([
                'account_type' =>
                    'FREE',
            ]);

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
            ->assertOk()
            ->assertJsonPath(
                'data.course.is_locked',
                true
            )
            ->assertJsonPath(
                'data.modules.0.lessons.0.is_locked',
                true
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_PREMIUM_REQUIRED'
            );
    }

    public function test_free_user_can_open_preview_lesson_in_premium_course(): void
    {
        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            true
        );

        $user =
            $this->createApiUser([
                'account_type' =>
                    'FREE',
            ]);

        $token =
            $this->issueBoundToken(
                $user
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
                'data.lesson.can_access',
                true
            );
    }

    public function test_premium_user_can_open_premium_lesson(): void
    {
        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            false
        );

        $user =
            $this->createApiUser([
                'account_type' =>
                    'PREMIUM',
            ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
            )
            ->assertOk();
    }
}
