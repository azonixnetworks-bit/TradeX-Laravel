<?php

namespace Tests\Feature\Api\Kyc;

use App\Models\KycSubmission;
use App\Services\Kyc\KycIdentityHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycIdorAndPrivacyTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_user_cannot_read_another_users_kyc_submission(): void
    {
        $owner = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $other = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $identityNumber =
            '199512345678';

        $submission =
            $this->createPendingSubmission(
                $owner->getKey(),
                $identityNumber
            );

        $otherToken =
            $this->issueBoundToken(
                $other
            );

        $this
            ->withToken(
                $otherToken
            )
            ->getJson(
                '/api/v1/kyc/submissions/'
                .$submission->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'KYC_SUBMISSION_NOT_FOUND'
            );
    }

    public function test_same_active_identity_cannot_be_claimed_by_different_user(): void
    {
        Storage::fake(
            'private'
        );

        $firstUser =
            $this->createApiUser([
                'country_code' => 'LK',
            ]);

        $secondUser =
            $this->createApiUser([
                'country_code' => 'LK',
            ]);

        $identityNumber =
            '199512345678';

        $this->createPendingSubmission(
            $firstUser->getKey(),
            $identityNumber
        );

        $secondToken =
            $this->issueBoundToken(
                $secondUser
            );

        $this
            ->withToken(
                $secondToken
            )
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaNicPayload(
                    $identityNumber
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
                'KYC_IDENTITY_CONFLICT'
            );

        $this->assertDatabaseCount(
            'kyc_submissions',
            1
        );

        $this->assertSame(
            [],
            Storage::disk(
                'private'
            )->allFiles()
        );
    }

    public function test_user_visible_rejection_reason_does_not_expose_internal_notes_or_identity_data(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $identityNumber =
            '199512345678';

        $submission =
            KycSubmission::query()
                ->create([
                    'user_id' =>
                        $user->getKey(),

                    'country_code' =>
                        'LK',

                    'document_type' =>
                        'NATIONAL_ID',

                    'identity_data' => [
                        'legal_name' =>
                            'Private Identity Name',

                        'date_of_birth' =>
                            '1995-05-15',

                        'identity_number' =>
                            $identityNumber,

                        'address' =>
                            'Private Identity Address',
                    ],

                    'identity_number_hash' =>
                        app(
                            KycIdentityHasher::class
                        )->hash(
                            $identityNumber,
                            'LK',
                            'NATIONAL_ID'
                        ),

                    'status' =>
                        KycSubmission::STATUS_REJECTED,

                    'rejection_reason' =>
                        'Please upload a clearer document.',

                    'internal_notes' =>
                        'Possible duplicate; investigate manually.',

                    'submitted_at' =>
                        now(),

                    'reviewed_at' =>
                        now(),
                ]);

        $response = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/submissions/'
                .$submission->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.submission.rejection_reason',
                'Please upload a clearer document.'
            )
            ->assertJsonPath(
                'data.submission.status',
                KycSubmission::STATUS_REJECTED
            );

        $payload = json_encode(
            $response->json(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'Possible duplicate; investigate manually.',
            $payload
        );

        $this->assertStringNotContainsString(
            $identityNumber,
            $payload
        );

        $this->assertStringNotContainsString(
            'Private Identity Name',
            $payload
        );

        $this->assertStringNotContainsString(
            'Private Identity Address',
            $payload
        );
    }

    private function createPendingSubmission(
        int $userId,
        string $identityNumber
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
                        'KYC Owner',

                    'date_of_birth' =>
                        '1995-05-15',

                    'identity_number' =>
                        $identityNumber,

                    'address' =>
                        'KYC Owner Address',
                ],

                'identity_number_hash' =>
                    app(
                        KycIdentityHasher::class
                    )->hash(
                        $identityNumber,
                        'LK',
                        'NATIONAL_ID'
                    ),

                'status' =>
                    KycSubmission::STATUS_PENDING_REVIEW,

                'submitted_at' =>
                    now(),
            ]);
    }
}
