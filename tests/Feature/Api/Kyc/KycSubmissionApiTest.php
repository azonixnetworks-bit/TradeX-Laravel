<?php

namespace Tests\Feature\Api\Kyc;

use App\Models\AuditLog;
use App\Models\KycDocument;
use App\Models\KycSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycSubmissionApiTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_sri_lanka_nic_submission_is_created_pending_and_stored_privately(): void
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

        $identityNumber =
            '199512345678';

        $response = $this
            ->withToken($token)
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
            ->assertCreated()
            ->assertJsonPath(
                'data.submission.status',
                KycSubmission::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.submission.country_code',
                'LK'
            )
            ->assertJsonPath(
                'data.submission.document_type',
                'NATIONAL_ID'
            )
            ->assertJsonPath(
                'data.submission.can_resubmit',
                false
            )
            ->assertJsonCount(
                3,
                'data.submission.documents'
            );

        $submission =
            KycSubmission::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->with(
                    'documents'
                )
                ->firstOrFail();

        $this->assertSame(
            KycSubmission::STATUS_PENDING_REVIEW,
            $submission->status
        );

        $this->assertSame(
            $identityNumber,
            $submission
                ->identity_data[
                    'identity_number'
                ]
        );

        $this->assertCount(
            3,
            $submission->documents
        );

        foreach (
            $submission->documents
            as $document
        ) {
            $this->assertSame(
                'private',
                $document->storage_disk
            );

            Storage::disk(
                'private'
            )->assertExists(
                $document->storage_path
            );
        }

        $payload = json_encode(
            $response->json(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $identityNumber,
            $payload
        );

        $this->assertStringNotContainsString(
            'TradeX KYC User',
            $payload
        );

        $this->assertStringNotContainsString(
            '10 Test Street, Colombo',
            $payload
        );

        foreach (
            $submission->documents
            as $document
        ) {
            $this->assertStringNotContainsString(
                $document->storage_path,
                $payload
            );

            $this->assertStringNotContainsString(
                $document->sha256,
                $payload
            );
        }

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'KYC_SUBMISSION_CREATED'
                )
                ->where(
                    'module',
                    'KYC'
                )
                ->where(
                    'target_id',
                    $submission->getKey()
                )
                ->firstOrFail();

        $auditPayload = json_encode(
            [
                'old' =>
                    $audit->old_values,
                'new' =>
                    $audit->new_values,
            ],
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $identityNumber,
            $auditPayload
        );

        $this->assertStringNotContainsString(
            'TradeX KYC User',
            $auditPayload
        );
    }

    public function test_passport_submission_does_not_require_identity_back(): void
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
                $this
                    ->sriLankaPassportPayload(),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.submission.document_type',
                'PASSPORT'
            )
            ->assertJsonCount(
                2,
                'data.submission.documents'
            );

        $this->assertDatabaseCount(
            'kyc_documents',
            2
        );
    }

    public function test_sensitive_kyc_values_are_encrypted_at_rest(): void
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

        $identityNumber =
            '200012345678';

        $this
            ->withToken($token)
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
            ->assertCreated();

        $submission =
            KycSubmission::query()
                ->with(
                    'documents'
                )
                ->firstOrFail();

        $rawSubmission =
            DB::table(
                'kyc_submissions'
            )
                ->where(
                    'id',
                    $submission->getKey()
                )
                ->first();

        $this->assertNotNull(
            $rawSubmission
        );

        $rawIdentityData =
            (string) $rawSubmission
                ->identity_data;

        $this->assertNotSame(
            json_encode(
                $submission
                    ->identity_data,
                JSON_THROW_ON_ERROR
            ),
            $rawIdentityData
        );

        $this->assertStringNotContainsString(
            $identityNumber,
            $rawIdentityData
        );

        $this->assertNotSame(
            $identityNumber,
            (string) $rawSubmission
                ->identity_number_hash
        );

        $this->assertSame(
            64,
            strlen(
                (string) $rawSubmission
                    ->identity_number_hash
            )
        );

        $document =
            $submission
                ->documents
                ->first();

        $this->assertInstanceOf(
            KycDocument::class,
            $document
        );

        $rawDocument =
            DB::table(
                'kyc_documents'
            )
                ->where(
                    'id',
                    $document->getKey()
                )
                ->first();

        $this->assertNotNull(
            $rawDocument
        );

        $this->assertNotSame(
            $document->storage_path,
            (string) $rawDocument
                ->storage_path
        );

        $this->assertStringNotContainsString(
            $document->storage_path,
            (string) $rawDocument
                ->storage_path
        );

        if (
            filled(
                $document
                    ->original_name
            )
        ) {
            $this->assertNotSame(
                $document->original_name,
                (string) $rawDocument
                    ->original_name
            );
        }
    }

    public function test_status_changes_to_pending_after_submission(): void
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

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/status'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                KycSubmission::STATUS_PENDING_REVIEW
            )
            ->assertJsonPath(
                'data.can_submit',
                false
            );
    }
}
