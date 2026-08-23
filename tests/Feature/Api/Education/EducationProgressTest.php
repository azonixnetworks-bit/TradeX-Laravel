<?php

namespace Tests\Feature\Api\Education;

use App\Models\UserLessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationProgressTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;

    public function test_user_can_update_and_complete_accessible_lesson_progress(): void
    {
        [
            $course,
            ,
            $lesson,
            $video,
        ] = $this->createEducationTree();

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
            ->patchJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'/progress',
                [
                    'status' =>
                        UserLessonProgress::STATUS_IN_PROGRESS,

                    'last_video_uuid' =>
                        $video->uuid,

                    'last_position_seconds' =>
                        45,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                UserLessonProgress::STATUS_IN_PROGRESS
            )
            ->assertJsonPath(
                'data.last_position_seconds',
                45
            );

        $this
            ->withToken(
                $token
            )
            ->patchJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'/progress',
                [
                    'status' =>
                        UserLessonProgress::STATUS_COMPLETED,

                    'last_video_uuid' =>
                        $video->uuid,

                    'last_position_seconds' =>
                        120,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                UserLessonProgress::STATUS_COMPLETED
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
                'data.course.progress.percent',
                100
            );
    }

    public function test_completed_lesson_is_not_downgraded_to_in_progress(): void
    {
        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree();

        $user =
            $this->createApiUser();

        UserLessonProgress::query()
            ->create([
                'user_id' =>
                    $user->getKey(),

                'lesson_id' =>
                    $lesson->getKey(),

                'status' =>
                    UserLessonProgress::STATUS_COMPLETED,

                'started_at' =>
                    now()->subHour(),

                'completed_at' =>
                    now(),

                'last_activity_at' =>
                    now(),
            ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->patchJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'/progress',
                [
                    'status' =>
                        UserLessonProgress::STATUS_IN_PROGRESS,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                UserLessonProgress::STATUS_COMPLETED
            );
    }
}
