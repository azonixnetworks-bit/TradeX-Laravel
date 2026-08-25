<?php

namespace Tests\Feature\Api\Kyc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycAuthenticationAndRequirementsTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_kyc_routes_require_authentication(): void
    {
        $uuid = (string) Str::uuid();

        $this
            ->getJson(
                '/api/v1/kyc/status'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/v1/kyc/requirements'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/v1/kyc/submissions/'
                .$uuid
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/kyc/submissions'
            )
            ->assertUnauthorized();
    }

    public function test_sri_lanka_requirements_are_country_aware(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/requirements'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.country_code',
                'LK'
            )
            ->assertJsonPath(
                'data.country_name',
                'Sri Lanka'
            )
            ->assertJsonFragment([
                'code' =>
                    'NATIONAL_ID',
            ])
            ->assertJsonFragment([
                'code' =>
                    'PASSPORT',
            ])
            ->assertJsonFragment([
                'max_kilobytes' =>
                    5120,
            ]);
    }

    public function test_india_requirements_include_configured_documents_but_not_aadhaar(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'IN',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/requirements'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.country_code',
                'IN'
            )
            ->assertJsonFragment([
                'code' =>
                    'PASSPORT',
            ])
            ->assertJsonFragment([
                'code' =>
                    'DRIVING_LICENCE',
            ])
            ->assertJsonFragment([
                'code' =>
                    'PAN_CARD',
            ])
            ->assertJsonMissing([
                'code' =>
                    'AADHAAR',
            ]);
    }

    public function test_unsupported_country_is_rejected_without_guessing_requirements(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'US',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/requirements'
            )
            ->assertStatus(
                422
            )
            ->assertJsonPath(
                'error.code',
                'KYC_COUNTRY_NOT_SUPPORTED'
            );
    }

    public function test_status_is_not_started_before_first_submission(): void
    {
        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/status'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'NOT_STARTED'
            )
            ->assertJsonPath(
                'data.can_submit',
                true
            )
            ->assertJsonPath(
                'data.submission',
                null
            );
    }
}
