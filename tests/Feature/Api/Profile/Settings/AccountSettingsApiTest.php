<?php

namespace Tests\Feature\Api\Profile\Settings;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AccountSettingsApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_overview_reuses_safe_profile_contract_and_adds_security_summary(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'language_code' => 'ta',
        ]);

        $user->profile()->create([
            'telegram_username' => 'tradex_user',
            'paypal_email' => 'payments@example.test',
            'usdt_trc20_address' => 'TXYZ1234567890ABCDEFGHIJK',
            'bank_name' => 'Example Bank',
            'bank_branch' => 'Colombo',
            'bank_account_holder' => 'Profile User',
            'bank_account_number' => '123456789012',
            'financial_updated_at' => now(),
        ]);

        $token = $this->issueBoundToken($user);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/profile/settings')
            ->assertOk()
            ->assertJsonPath('data.profile.identity.uuid', $user->uuid)
            ->assertJsonPath('data.profile.preferences.language_code', 'ta')
            ->assertJsonPath('data.profile.financial.paypal.configured', true)
            ->assertJsonPath('data.account_security.verification.totp_confirmed', true)
            ->assertJsonPath('data.account_security.device_policy.maximum_active_mobile_devices', 1)
            ->assertJsonPath('data.account_security.device_policy.maximum_active_desktop_devices', 1)
            ->assertJsonPath('data.account_security.current_device.is_current', true);

        $payload = json_encode($response->json('data'), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('payments@example.test', $payload);
        $this->assertStringNotContainsString('123456789012', $payload);
        $this->assertNoInternalIdKey($response->json('data'));
    }

    public function test_device_list_is_owned_and_hides_private_device_security_fields(): void
    {
        $user = $this->createApiUser();
        $other = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $desktop = $this->createDesktopDevice($user);
        $otherDevice = $this->createDesktopDevice($other);

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/profile/settings/devices')
            ->assertOk()
            ->assertJsonPath('data.policy.one_mobile_slot', true)
            ->assertJsonPath('data.policy.one_desktop_slot', true);

        $items = $response->json('data.items');
        $this->assertCount(2, $items);

        $encoded = json_encode($items, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString($desktop->uuid, $encoded);
        $this->assertStringNotContainsString($otherDevice->uuid, $encoded);
        $this->assertStringNotContainsString($desktop->installation_id_hash, $encoded);
        $this->assertStringNotContainsString('PUBLIC-KEY-SECRET', $encoded);
        $this->assertStringNotContainsString('10.10.10.10', $encoded);
        $this->assertNoInternalIdKey($items);
    }

    public function test_remote_revoke_requires_totp_revokes_target_access_and_preserves_current_session(): void
    {
        $user = $this->createApiUser();
        $currentToken = $this->issueBoundToken($user);
        $currentDevice = $user->devices()->where('device_slot', UserDevice::SLOT_MOBILE)->firstOrFail();
        $desktop = $this->createDesktopDevice($user);

        $desktopToken = $user->createToken(
            'desktop-test',
            ['mobile-api'],
            now()->addMinutes(15)
        );
        $desktopAccessId = $desktopToken->accessToken->getKey();
        $desktopToken->accessToken->forceFill([
            'user_device_id' => $desktop->getKey(),
            'session_uuid' => (string) Str::uuid(),
        ])->save();

        $totp = app(TotpService::class)->codeAt(
            (string) $user->totp_secret,
            time()
        );

        $this
            ->withToken($currentToken)
            ->patchJson(
                '/api/v1/profile/settings/devices/'.$desktop->uuid.'/revoke',
                ['totp_code' => $totp]
            )
            ->assertOk()
            ->assertJsonPath('data.device.uuid', $desktop->uuid)
            ->assertJsonPath('data.device.active', false)
            ->assertJsonPath('data.current_session_preserved', true);

        $this->assertNotNull($desktop->fresh()->revoked_at);
        $this->assertTrue((bool) $currentDevice->fresh()->is_active);
        $this->assertNull($currentDevice->fresh()->revoked_at);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $desktopAccessId]);

        $this
            ->withToken($currentToken)
            ->getJson('/api/v1/profile/settings/security')
            ->assertOk()
            ->assertJsonPath('data.device_policy.active_desktop_devices', 0);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'PROFILE_REMOTE_DEVICE_REVOKED',
            'module' => 'PROFILE',
        ]);
    }

    public function test_remote_revoke_prevents_cross_user_idor_and_current_device_misuse(): void
    {
        $user = $this->createApiUser();
        $other = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $otherDevice = $this->createDesktopDevice($other);

        $totp = app(TotpService::class)->codeAt(
            (string) $user->totp_secret,
            time()
        );

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/profile/settings/devices/'.$otherDevice->uuid.'/revoke',
                ['totp_code' => $totp]
            )
            ->assertNotFound()
            ->assertJsonPath('error.code', 'DEVICE_NOT_AVAILABLE');

        $current = $user->devices()->firstOrFail();

        $this
            ->withToken($token)
            ->patchJson(
                '/api/v1/profile/settings/devices/'.$current->uuid.'/revoke',
                ['totp_code' => $totp]
            )
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CURRENT_DEVICE_REVOKE_ROUTE_REQUIRED');

        $this->assertTrue((bool) $current->fresh()->is_active);
        $this->assertTrue((bool) $otherDevice->fresh()->is_active);
    }

    private function createDesktopDevice(User $user): UserDevice
    {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'device_slot' => UserDevice::SLOT_DESKTOP,
            'platform' => UserDevice::PLATFORM_WINDOWS,
            'device_name' => 'Office PC',
            'device_model' => 'TradeX Test PC',
            'os_version' => 'Windows 11',
            'app_version' => '1.0.0',
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'device_public_key' => 'PUBLIC-KEY-SECRET',
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now()->subDay(),
            'trusted_at' => now()->subDay(),
            'last_seen_at' => now()->subMinute(),
            'last_login_at' => now()->subHour(),
            'last_login_ip' => '10.10.10.10',
        ]);
    }

    private function assertNoInternalIdKey(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $nested) {
            if (is_string($key)) {
                $this->assertNotSame(
                    'id',
                    $key,
                    'API-11 must not expose internal numeric id fields.'
                );
            }

            $this->assertNoInternalIdKey($nested);
        }
    }
}
