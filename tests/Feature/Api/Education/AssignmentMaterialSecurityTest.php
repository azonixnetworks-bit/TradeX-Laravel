<?php

namespace Tests\Feature\Api\Education;

use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAssignmentContent;
use Tests\Concerns\CreatesEducationContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AssignmentMaterialSecurityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesEducationContent;
    use CreatesAssignmentContent;

    public function test_premium_user_can_download_protected_material_without_storage_path_leakage(): void
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

        $detail =
            $this
                ->withToken(
                    $token
                )
                ->getJson(
                    '/api/v1/education/assignments/'
                    .$assignment->uuid
                );

        $detail
            ->assertOk()
            ->assertJsonPath(
                'data.assignment.material.available',
                true
            );

        $this->assertStringNotContainsString(
            (string) $assignment->material_path,
            $detail->getContent()
        );

        $response =
            $this
                ->withToken(
                    $token
                )
                ->get(
                    '/api/v1/education/assignments/'
                    .$assignment->uuid
                    .'/material'
                );

        $response
            ->assertOk()
            ->assertHeader(
                'X-TradeX-Content-Protection',
                'assignment-material'
            )
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get(
                'Cache-Control'
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
                    'ASSIGNMENT_MATERIAL_DOWNLOAD_REQUESTED',

                'target_type' =>
                    'ASSIGNMENT',

                'target_id' =>
                    $assignment->getKey(),
            ]
        );
    }

    public function test_free_preview_user_cannot_download_assignment_material(): void
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
            ->get(
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'/material'
            )
            ->assertForbidden();
    }

    public function test_assignment_without_material_returns_controlled_not_found(): void
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
                [],
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
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'/material'
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'ASSIGNMENT_MATERIAL_NOT_AVAILABLE'
            );
    }

    public function test_draft_assignment_and_in_app_student_upload_route_are_not_exposed(): void
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
                    'status' =>
                        Assignment::STATUS_DRAFT,

                    'published_at' =>
                        null,
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
                '/api/v1/education/assignments/'
                .$assignment->uuid
            )
            ->assertNotFound();

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/education/assignments/'
                .$assignment->uuid
                .'/submit',
                [
                    'file' =>
                        'must-never-be-accepted',
                ]
            )
            ->assertNotFound();
    }
}
