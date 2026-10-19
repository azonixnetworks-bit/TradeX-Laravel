<?php

namespace Tests\Feature\Api\Security;

use App\Models\UserDevice;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesQuickUnlockCredential;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class QuickUnlockSecurityTest extends TestCase
{
    use CreatesQuickUnlockCredential;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_server_rejects_local_pin_and_biometric_secret_material(): void
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

        $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                [
                    'enabled' => true,
                    'method' => 'PIN',
                    'totp_code' => $totp,
                    'pin' => '1234',
                    'pin_hash' => 'should-never-arrive',
                    'biometric_data' => 'should-never-arrive',
                ]
            )
            ->assertUnprocessable();

        foreach (
            [
                'pin',
                'pin_hash',
                'pin_salt',
                'biometric_data',
                'fingerprint',
                'face_id',
                'local_secret',
                'refresh_token',
            ] as $column
        ) {
            $this->assertFalse(
                Schema::hasColumn(
                    'user_device_quick_unlock_settings',
                    $column
                )
            );
        }
    }

    public function test_changed_device_trust_epoch_requires_reenrollment(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $device = $user->devices()
            ->firstOrFail();
        $credential = $this->createQuickUnlockCredential();

        UserDeviceQuickUnlockSetting::query()
            ->create([
                'user_device_id' => $device->getKey(),
                'enabled' => true,
                'method' => 'PIN',
                'trusted_at_snapshot' => $device->trusted_at,
                'enrolled_at' => now(),
                'credential_id' => $credential['id'],
                'credential_algorithm' => $credential['algorithm'],
                'credential_public_key' => $credential['public_key'],
                'credential_rotated_at' => now(),
            ]);

        $device->forceFill([
            'trusted_at' => $device->trusted_at->copy()
                ->addMinute(),
        ])->save();

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
                true
            )
            ->assertJsonPath(
                'data.requires_reenrollment',
                true
            )
            ->assertJsonPath(
                'data.method',
                null
            );
    }

    public function test_quick_unlock_is_mobile_only(): void
    {
        $user = $this->createApiUser();

        $device =
            UserDevice::query()
                ->create([
                    'uuid' => (string) Str::uuid(),

                    'user_id' => $user->getKey(),

                    'device_slot' => UserDevice::SLOT_DESKTOP,

                    'platform' => UserDevice::PLATFORM_WINDOWS,

                    'device_name' => 'Desktop Test',

                    'installation_id_hash' => hash(
                        'sha256',
                        (string) Str::uuid()
                    ),

                    'is_trusted' => true,

                    'is_active' => true,

                    'registered_at' => now(),

                    'trusted_at' => now(),
                ]);

        $sessionUuid =
            (string) Str::uuid();

        $token =
            $user->createToken(
                'desktop-security-test',
                ['mobile-api'],
                now()->addMinutes(15)
            );

        $token->accessToken
            ->forceFill([
                'user_device_id' => $device->getKey(),

                'session_uuid' => $sessionUuid,
            ])
            ->save();

        $this->withToken(
            $token->plainTextToken
        )
            ->getJson(
                '/api/v1/security/quick-unlock'
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'QUICK_UNLOCK_MOBILE_ONLY'
            );
    }

    public function test_biometric_can_be_disabled_by_server_policy(): void
    {
        config([
            'tradex_mobile_security.quick_unlock.biometric_allowed' => false,
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
        $credential = $this->createQuickUnlockCredential();

        $this->withToken($token)
            ->patchJson(
                '/api/v1/security/quick-unlock',
                [
                    'enabled' => true,
                    'method' => 'BIOMETRIC',
                    'totp_code' => $totp,
                    'credential_id' => $credential['id'],
                    'credential_algorithm' => $credential['algorithm'],
                    'credential_public_key' => $credential['public_key'],
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'QUICK_UNLOCK_BIOMETRIC_DISABLED'
            );
    }
}
