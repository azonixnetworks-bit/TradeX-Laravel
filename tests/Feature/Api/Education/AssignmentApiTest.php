<?php

namespace Tests\Feature\Api\Education;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssignmentContent;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AssignmentApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;
    use CreatesAssignmentContent;

    public function test_premium_user_can_list_and_open_only_published_assignments(): void
    {
        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            false
        );

        $published =
            $this->createAssignment(
                $lesson
            );

        $this->createAssignment(
            $lesson,
            [
                'status' =>
                    Assignment::STATUS_DRAFT,

                'published_at' =>
                    null,

                'title' =>
                    'Hidden Draft Assignment',
            ],
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
                .'/assignments'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.count',
                1
            )
            ->assertJsonPath(
                'data.items.0.uuid',
                $published->uuid
            )
            ->assertJsonPath(
                'data.items.0.whatsapp.enabled',
                true
            )
            ->assertJsonPath(
                'data.items.0.material.available',
                true
            )
            ->assertJsonPath(
                'data.submission_policy.student_upload_to_tradex',
                false
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/assignments/'
                .$published->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.assignment.uuid',
                $published->uuid
            )
            ->assertJsonPath(
                'data.assignment.submission_method',
                Assignment::SUBMISSION_WHATSAPP
            )
            ->assertJsonPath(
                'data.submission_policy.submission_status_persisted',
                false
            );
    }

    public function test_preview_lesson_does_not_bypass_assignment_premium_gate(): void
    {
        [
            ,
            ,
            $lesson,
        ] = $this->createEducationTree(
            Course::ACCESS_PREMIUM,
            true
        );

        $assignment =
            $this->createAssignment(
                $lesson
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
            ->assertOk();

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/lessons/'
                .$lesson->uuid
                .'/assignments'
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_ASSIGNMENT_PREMIUM_REQUIRED'
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/education/assignments/'
                .$assignment->uuid
            )
            ->assertForbidden()
            ->assertJsonPath(
                'error.code',
                'EDUCATION_ASSIGNMENT_PREMIUM_REQUIRED'
            );
    }

    public function test_whatsapp_action_returns_external_submission_metadata_and_audits_without_marking_submitted(): void
    {
        config()->set(
            'tradex_education.assignments.whatsapp_recipient',
            '+94 77 123 4567'
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

        $response =
            $this
                ->withToken(
                    $token
                )
                ->postJson(
                    '/api/v1/education/assignments/'
                    .$assignment->uuid
                    .'/whatsapp-action'
                );

        $response
            ->assertOk()
            ->assertJsonPath(
                'data.submission_method',
                Assignment::SUBMISSION_WHATSAPP
            )
            ->assertJsonPath(
                'data.recipient_configured',
                true
            )
            ->assertJsonPath(
                'data.metadata.student_upload_to_tradex',
                false
            )
            ->assertJsonPath(
                'data.metadata.delivery_verified_by_tradex',
                false
            )
            ->assertJsonPath(
                'data.metadata.submission_status_persisted',
                false
            );

        $this->assertStringStartsWith(
            'https://wa.me/94771234567?text=',
            (string) $response->json(
                'data.whatsapp_url'
            )
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'USER',

                'actor_id' =>
                    $user->getKey(),

                'action' =>
                    'ASSIGNMENT_WHATSAPP_ACTION_PREPARED',

                'module' =>
                    'EDUCATION',

                'target_type' =>
                    'ASSIGNMENT',

                'target_id' =>
                    $assignment->getKey(),
            ]
        );

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'ASSIGNMENT_WHATSAPP_ACTION_PREPARED'
                )
                ->latest(
                    'created_at'
                )
                ->firstOrFail();

        $this->assertFalse(
            (bool) (
                $audit->new_values['delivery_verified_by_tradex']
                ?? true
            )
        );
    }

    public function test_whatsapp_action_rejects_assignment_with_submission_method_none(): void
    {
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
                $lesson,
                [
                    'submission_method' =>
                        Assignment::SUBMISSION_NONE,
                ]
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
            ->postJson(
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'/whatsapp-action'
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'error.code',
                'ASSIGNMENT_WHATSAPP_NOT_ENABLED'
            );
    }
}
