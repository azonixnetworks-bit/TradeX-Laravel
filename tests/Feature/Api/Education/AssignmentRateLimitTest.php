<?php

namespace Tests\Feature\Api\Education;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssignmentContent;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AssignmentRateLimitTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;
    use CreatesAssignmentContent;

    public function test_whatsapp_action_is_rate_limited_per_authenticated_user(): void
    {
        config()->set(
            'tradex_education.rate_limits.assignment_action_per_minute',
            1
        );

        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            false
        );

        $assignment =
            $this->createAssignment(
                $lesson
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

        $url =
            '/api/v1/education/assignments/'
            .$assignment->uuid
            .'/whatsapp-action';

        $this
            ->withToken(
                $token
            )
            ->postJson(
                $url
            )
            ->assertOk();

        $this
            ->withToken(
                $token
            )
            ->postJson(
                $url
            )
            ->assertStatus(
                429
            );
    }
}
