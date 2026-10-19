<?php

namespace Tests\Feature\Api\Security;

use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesQuickUnlockCredential;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class QuickUnlockRateLimitTest extends TestCase
{
    use CreatesQuickUnlockCredential;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_quick_unlock_change_has_dedicated_rate_limit(): void
    {
        config([
            'tradex_mobile_security.quick_unlock.rate_limits.change_per_minute' => 1,
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $user->refresh();

        $totp = app(
            TotpService::class
        )->codeAt(
            (string) $user->totp_secret,
            time()
        );

        $payload = [
            'enabled' => true,
            'method' => 'PIN',
            'totp_code' => $totp,
        ];
        $credential = $this->createQuickUnlockCredential();
        $payload += [
            'credential_id' => $credential['id'],
            'credential_algorithm' => $credential['algorithm'],
            'credential_public_key' => $credential['public_key'],
        ];

        $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                $payload
            )
            ->assertOk();

        $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                $payload
            )
            ->assertTooManyRequests();
    }
}
