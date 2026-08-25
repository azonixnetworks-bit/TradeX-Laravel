<?php

namespace Tests\Feature\Api\Kyc;

use App\Models\KycSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycStateAndResubmissionTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_duplicate_pending_submission_is_blocked(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    '199512345678'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $filesBefore =
            Storage::disk(
                'private'
            )->allFiles();

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaPassportPayload(
                    'N7654321'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'error.code',
                'KYC_SUBMISSION_ALREADY_PENDING'
            );

        $this->assertDatabaseCount(
            'kyc_submissions',
            1
        );

        $this->assertSame(
            $filesBefore,
            Storage::disk(
                'private'
            )->allFiles()
        );
    }

    public function test_verified_kyc_is_immutable_from_user_submission_flow(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $submission =
            KycSubmission::query()
                ->firstOrFail();

        $submission->forceFill([
            'status' =>
                KycSubmission::STATUS_VERIFIED,

            'reviewed_at' =>
                now(),
        ])->save();

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaPassportPayload(
                    'N9999999'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertStatus(
                409
            )
            ->assertJsonPath(
                'error.code',
                'KYC_ALREADY_VERIFIED'
            );

        $this->assertDatabaseCount(
            'kyc_submissions',
            1
        );

        $this->assertTrue(
            $user->fresh()
                ->hasVerifiedKyc()
        );
    }

    public function test_rejected_submission_can_be_resubmitted_with_history_preserved(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    '199512345678'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $first =
            KycSubmission::query()
                ->firstOrFail();

        $first->forceFill([
            'status' =>
                KycSubmission::STATUS_REJECTED,

            'rejection_reason' =>
                'Please upload a clearer identity document.',

            'internal_notes' =>
                'Internal reviewer note must stay private.',

            'reviewed_at' =>
                now(),
        ])->save();

        $response = $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaPassportPayload(
                    'N1234567'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.submission.status',
                KycSubmission::STATUS_PENDING_REVIEW
            );

        $second =
            KycSubmission::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            $first->getKey(),
            $second
                ->supersedes_submission_id
        );

        $this->assertDatabaseCount(
            'kyc_submissions',
            2
        );

        $payload = json_encode(
            $response->json(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'Internal reviewer note must stay private.',
            $payload
        );
    }

    public function test_needs_resubmission_state_allows_new_version(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    '200012345678'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $first =
            KycSubmission::query()
                ->firstOrFail();

        $first->forceFill([
            'status' =>
                KycSubmission::STATUS_NEEDS_RESUBMISSION,

            'rejection_reason' =>
                'Please retake the selfie.',

            'reviewed_at' =>
                now(),
        ])->save();

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    '200012345678'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $second =
            KycSubmission::query()
                ->latest(
                    'id'
                )
                ->firstOrFail();

        $this->assertSame(
            $first->getKey(),
            $second
                ->supersedes_submission_id
        );
    }
}
