<?php

namespace Tests\Feature\Api\Kyc;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesKycTestFiles;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class KycRateLimitTest extends TestCase
{
    use CreatesKycTestFiles;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_kyc_submit_has_dedicated_user_rate_limit(): void
    {
        Storage::fake(
            'private'
        );

        config()->set(
            'tradex_kyc.rate_limits.submit_per_minute',
            1
        );

        config()->set(
            'tradex_kyc.rate_limits.submit_per_hour',
            10
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

        $this
            ->withToken($token)
            ->post(
                '/api/v1/kyc/submissions',
                $this->sriLankaPassportPayload(
                    'N5555555'
                ),
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertStatus(
                429
            );
    }

    public function test_kyc_read_has_dedicated_user_rate_limit(): void
    {
        config()->set(
            'tradex_kyc.rate_limits.read_per_minute',
            1
        );

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
            ->assertOk();

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/kyc/status'
            )
            ->assertStatus(
                429
            );
    }
}
