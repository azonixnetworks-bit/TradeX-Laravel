<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PasswordRecoveryRateLimitTest extends TestCase
{
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePasswordRecoveryTesting();
    }

    public function test_start_has_dedicated_rate_limit(): void
    {
        config([
            'tradex_password_recovery.rate_limits.start_per_minute' => 1,
            'tradex_password_recovery.rate_limits.start_per_hour_ip' => 50,
        ]);

        $user = $this->createApiUser();

        $payload = [
            'email' => $user->email,
            'whatsapp_number' =>
                $user->whatsapp_number,
        ];

        $this->postJson(
            '/api/v1/auth/password-recovery/start',
            $payload
        )->assertAccepted();

        $this->postJson(
            '/api/v1/auth/password-recovery/start',
            $payload
        )->assertTooManyRequests();
    }

    public function test_reset_requires_strong_password_contract(): void
    {
        $user = $this->createApiUser();

        $flow =
            $this->completePasswordRecoveryFactors(
                $user
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            [
                ...$flow,
                'password' => 'weakpassword',
                'password_confirmation' =>
                    'weakpassword',
            ]
        )->assertUnprocessable();
    }
}
