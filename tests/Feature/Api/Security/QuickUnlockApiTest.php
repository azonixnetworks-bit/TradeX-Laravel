<?php

namespace Tests\Feature\Api\Security;

use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesQuickUnlockCredential;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class QuickUnlockApiTest extends TestCase
{
    use CreatesQuickUnlockCredential;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_mobile_user_can_read_quick_unlock_policy_and_disabled_state(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/security/quick-unlock'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.enabled',
                false
            )
            ->assertJsonPath(
                'data.enrolled',
                false
            )
            ->assertJsonPath(
                'data.requires_reenrollment',
                false
            )
            ->assertJsonPath(
                'data.policy.pin_length',
                4
            )
            ->assertJsonPath(
                'data.policy.background_lock_seconds',
                1800
            )
            ->assertJsonPath(
                'data.policy.pin_transport',
                'NEVER_SEND_TO_SERVER'
            )
            ->assertJsonPath(
                'data.policy.biometric_transport',
                'NEVER_SEND_TO_SERVER'
            )
            ->assertJsonPath(
                'data.policy.refresh_endpoint',
                '/api/v1/auth/refresh'
            );
    }

    public function test_user_can_enable_pin_quick_unlock_with_fresh_totp(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $user->refresh();

        $totp = app(
            TotpService::class
        )->codeAt(
            (string) $user->totp_secret,
            time()
        );
        $credential = $this->createQuickUnlockCredential();

        $response = $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                [
                    'enabled' => true,
                    'method' => 'pin',
                    'totp_code' => $totp,
                    'credential_id' => $credential['id'],
                    'credential_algorithm' => $credential['algorithm'],
                    'credential_public_key' => $credential['public_key'],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.enabled',
                true
            )
            ->assertJsonPath(
                'data.method',
                'PIN'
            )
            ->assertJsonPath(
                'data.requires_reenrollment',
                false
            );

        $device = $user->devices()
            ->firstOrFail();

        $this->assertDatabaseHas(
            'user_device_quick_unlock_settings',
            [
                'user_device_id' => $device->getKey(),
                'enabled' => 1,
                'method' => 'PIN',
                'credential_id' => $credential['id'],
            ]
        );

        $setting =
            UserDeviceQuickUnlockSetting::query()
                ->where(
                    'user_device_id',
                    $device->getKey()
                )
                ->firstOrFail();

        $this->assertNotNull(
            $setting->trusted_at_snapshot
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'USER',
                'actor_id' => $user->getKey(),
                'action' => 'QUICK_UNLOCK_ENABLED',
                'module' => 'SECURITY',
                'target_type' => 'USER_DEVICE',
                'target_id' => $device->getKey(),
            ]
        );
    }

    public function test_user_can_enable_fingerprint_and_passcode_together(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $user->refresh();
        $totp = app(TotpService::class)->codeAt(
            (string) $user->totp_secret,
            time()
        );
        $credential = $this->createQuickUnlockCredential();

        $this->withToken($token)
            ->patchJson('/api/v1/security/quick-unlock', [
                'enabled' => true,
                'method' => 'both',
                'totp_code' => $totp,
                'credential_id' => $credential['id'],
                'credential_algorithm' => $credential['algorithm'],
                'credential_public_key' => $credential['public_key'],
            ])
            ->assertOk()
            ->assertJsonPath('data.method', 'BOTH');

        $this->assertDatabaseHas('user_device_quick_unlock_settings', [
            'user_device_id' => $user->devices()->firstOrFail()->getKey(),
            'enabled' => 1,
            'method' => UserDeviceQuickUnlockSetting::METHOD_BOTH,
            'credential_id' => $credential['id'],
        ]);
    }

    public function test_user_can_disable_existing_quick_unlock_with_totp(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $device = $user->devices()
            ->firstOrFail();

        UserDeviceQuickUnlockSetting::query()
            ->create([
                'user_device_id' => $device->getKey(),
                'enabled' => true,
                'method' => 'BIOMETRIC',
                'trusted_at_snapshot' => $device->trusted_at,
                'enrolled_at' => now(),
            ]);

        $user->refresh();

        $totp = app(
            TotpService::class
        )->codeAt(
            (string) $user->totp_secret,
            time()
        );

        $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                [
                    'enabled' => false,
                    'totp_code' => $totp,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.enabled',
                false
            )
            ->assertJsonPath(
                'data.method',
                null
            )
            ->assertJsonPath(
                'data.enrolled',
                false
            );

        $this->assertDatabaseHas(
            'user_device_quick_unlock_settings',
            [
                'user_device_id' => $device->getKey(),
                'enabled' => 0,
                'method' => null,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'USER',
                'actor_id' => $user->getKey(),
                'action' => 'QUICK_UNLOCK_DISABLED',
                'module' => 'SECURITY',
            ]
        );
    }
}
