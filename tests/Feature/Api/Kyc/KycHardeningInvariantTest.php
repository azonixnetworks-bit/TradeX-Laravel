<?php

namespace Tests\Feature\Api\Kyc;

use App\Models\KycSubmission;
use App\Services\Kyc\KycIdentityHasher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycHardeningInvariantTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_identity_hash_is_namespaced_by_country_and_document_type(): void
    {
        $hasher = app(
            KycIdentityHasher::class
        );

        $lkNic = $hasher->hash(
            'ABC-123',
            'LK',
            'NATIONAL_ID'
        );

        $lkNicNormalized =
            $hasher->hash(
                'abc 123',
                'lk',
                'national_id'
            );

        $inPan = $hasher->hash(
            'ABC-123',
            'IN',
            'PAN_CARD'
        );

        $this->assertSame(
            $lkNic,
            $lkNicNormalized
        );

        $this->assertNotSame(
            $lkNic,
            $inPan
        );

        $this->assertSame(
            64,
            strlen(
                $lkNic
            )
        );
    }

    public function test_database_unique_constraint_blocks_two_active_claims_for_same_identity(): void
    {
        $firstUser =
            $this->createApiUser([
                'country_code' => 'LK',
            ]);

        $secondUser =
            $this->createApiUser([
                'country_code' => 'LK',
            ]);

        $hash = app(
            KycIdentityHasher::class
        )->hash(
            '199512345678',
            'LK',
            'NATIONAL_ID'
        );

        $this->createSubmission(
            $firstUser->getKey(),
            $hash,
            KycSubmission::STATUS_PENDING_REVIEW
        );

        $this->expectException(
            QueryException::class
        );

        $this->createSubmission(
            $secondUser->getKey(),
            $hash,
            KycSubmission::STATUS_VERIFIED
        );
    }

    public function test_rejected_state_releases_active_identity_claim_but_keeps_evidence_hash(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $hash = app(
            KycIdentityHasher::class
        )->hash(
            '200012345678',
            'LK',
            'NATIONAL_ID'
        );

        $submission =
            $this->createSubmission(
                $user->getKey(),
                $hash,
                KycSubmission::STATUS_PENDING_REVIEW
            );

        $this->assertSame(
            $hash,
            $submission
                ->active_identity_hash
        );

        $submission->forceFill([
            'status' =>
                KycSubmission::STATUS_REJECTED,
        ])->save();

        $submission->refresh();

        $this->assertSame(
            $hash,
            $submission
                ->identity_number_hash
        );

        $this->assertNull(
            $submission
                ->active_identity_hash
        );
    }

    public function test_needs_resubmission_state_releases_active_identity_claim(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $hash = app(
            KycIdentityHasher::class
        )->hash(
            '200099999999',
            'LK',
            'NATIONAL_ID'
        );

        $submission =
            $this->createSubmission(
                $user->getKey(),
                $hash,
                KycSubmission::STATUS_PENDING_REVIEW
            );

        $submission->forceFill([
            'status' =>
                KycSubmission::STATUS_NEEDS_RESUBMISSION,
        ])->save();

        $this->assertNull(
            $submission
                ->fresh()
                ->active_identity_hash
        );
    }

    public function test_verified_state_retains_active_identity_claim(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $hash = app(
            KycIdentityHasher::class
        )->hash(
            '198812345678',
            'LK',
            'NATIONAL_ID'
        );

        $submission =
            $this->createSubmission(
                $user->getKey(),
                $hash,
                KycSubmission::STATUS_PENDING_REVIEW
            );

        $submission->forceFill([
            'status' =>
                KycSubmission::STATUS_VERIFIED,
        ])->save();

        $this->assertSame(
            $hash,
            $submission
                ->fresh()
                ->active_identity_hash
        );
    }

    public function test_kyc_submission_does_not_activate_premium_entitlement(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
            'account_type' => 'FREE',
            'premium_activated_at' => null,
        ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    '199577777777'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated();

        $user->refresh();

        $this->assertSame(
            'FREE',
            $user->account_type
        );

        $this->assertNull(
            $user->premium_activated_at
        );
    }

    private function createSubmission(
        int $userId,
        string $identityHash,
        string $status
    ): KycSubmission {
        return KycSubmission::query()
            ->create([
                'user_id' =>
                    $userId,

                'country_code' =>
                    'LK',

                'document_type' =>
                    'NATIONAL_ID',

                'identity_data' => [
                    'legal_name' =>
                        'Hardening User',

                    'date_of_birth' =>
                        '1990-01-01',

                    'identity_number' =>
                        'MASKED-IN-TEST',

                    'address' =>
                        'Hardening Address',
                ],

                'identity_number_hash' =>
                    $identityHash,

                'status' =>
                    $status,

                'submitted_at' =>
                    now(),
            ]);
    }
}
