<?php

namespace Tests\Feature\Api\Kyc;

use App\Exceptions\KycApiException;
use App\Models\KycDocument;
use App\Services\Kyc\KycSubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycValidationAndStorageSecurityTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_nic_submission_rejects_missing_back_document(): void
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

        $payload =
            $this->sriLankaNicPayload();

        unset(
            $payload[
                'identity_back'
            ]
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $payload,
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'identity_back',
            ]);

        $this->assertDatabaseCount(
            'kyc_submissions',
            0
        );

        $this->assertSame(
            [],
            Storage::disk(
                'private'
            )->allFiles()
        );
    }

    public function test_identity_document_rejects_plain_text_mime(): void
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

        $payload =
            $this->sriLankaNicPayload();

        $payload[
            'identity_front'
        ] = $this->kycText(
            'fake-id.txt'
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $payload,
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'identity_front',
            ]);

        $this->assertDatabaseCount(
            'kyc_submissions',
            0
        );
    }

    public function test_selfie_rejects_pdf_even_when_identity_documents_allow_pdf(): void
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

        $payload =
            $this->sriLankaPassportPayload();

        $payload[
            'selfie'
        ] = $this->kycPdf(
            'selfie.pdf'
        );

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $payload,
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'selfie',
            ]);

        $this->assertDatabaseCount(
            'kyc_submissions',
            0
        );
    }

    public function test_storage_cleanup_removes_already_written_file_when_later_document_fails(): void
    {
        Storage::fake(
            'private'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $service = app(
            KycSubmissionService::class
        );

        $request =
            Request::create(
                '/api/v1/kyc/submissions',
                'POST'
            );

        try {
            $service->submit(
                $user,
                'NATIONAL_ID',
                [
                    'legal_name' =>
                        'Cleanup User',

                    'date_of_birth' =>
                        '1990-01-01',

                    'identity_number' =>
                        '900101123V',

                    'address' =>
                        'Cleanup Test Address',
                ],
                [
                    KycDocument::KIND_IDENTITY_FRONT =>
                        $this->kycPng(
                            'front.png'
                        ),

                    KycDocument::KIND_IDENTITY_BACK =>
                        $this->kycText(
                            'back.txt'
                        ),

                    KycDocument::KIND_SELFIE =>
                        $this->kycPng(
                            'selfie.png'
                        ),
                ],
                $request
            );

            $this->fail(
                'Expected KycApiException was not thrown.'
            );
        } catch (
            KycApiException $exception
        ) {
            $this->assertSame(
                'KYC_DOCUMENT_MIME_INVALID',
                $exception->apiCode
            );
        }

        $this->assertSame(
            [],
            Storage::disk(
                'private'
            )->allFiles()
        );

        $this->assertDatabaseCount(
            'kyc_submissions',
            0
        );

        $this->assertDatabaseCount(
            'kyc_documents',
            0
        );
    }
}
