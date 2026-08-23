<?php

namespace Tests\Feature\Api\Profile\Settings;

use App\Models\UserDevice;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AccountSettingsRateLimitTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_remote_device_revoke_has_dedicated_sensitive_rate_limit(): void
    {
        config()->set(
            'tradex_account_settings_api.rate_limits.sensitive_per_minute',
            1
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $desktop = $this->desktop($user->getKey());

        $totp = app(TotpService::class)->codeAt(
            (string) $user->totp_secret,
            time()
        );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/profile/settings/devices/'.$desktop->uuid.'/revoke',
                ['totp_code' => $totp]
            )
            ->assertOk();

        $second = $this->desktop($user->getKey());

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/profile/settings/devices/'.$second->uuid.'/revoke',
                ['totp_code' => '000000']
            )
            ->assertStatus(429);
    }

    private function desktop(int $userId): UserDevice
    {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'device_slot' => UserDevice::SLOT_DESKTOP,
            'platform' => UserDevice::PLATFORM_WINDOWS,
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);
    }
}
