<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\DeviceBindingService;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerDeviceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_two_trusted_devices_without_sensitive_values(): void
    {
        $user = $this->createTotpUser();
        $oldInstallationId = (string) Str::uuid();

        $old = $this->createTrustedDevice(
            $user,
            $oldInstallationId,
            'Old Phone'
        );

        $login = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload($user)
        )->assertOk();

        $accessToken = (string) $login->json('data.access_token');
        $currentUuid = (string) $login->json('data.device.uuid');

        $response = $this
            ->withToken($accessToken)
            ->getJson('/api/v1/auth/devices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.max_trusted_devices', 2)
            ->assertJsonCount(2, 'data.devices');

        $devices = collect($response->json('data.devices'));

        $this->assertTrue(
            (bool) $devices
                ->firstWhere('uuid', $currentUuid)['is_current']
        );

        $this->assertFalse(
            (bool) $devices
                ->firstWhere('uuid', $old->uuid)['is_current']
        );

        $json = (string) $response->getContent();

        foreach ([
            'installation_id_hash',
            'device_public_key',
            'access_token',
            'refresh_token',
            'token_hash',
            'session_uuid',
            'totp_secret',
            'password',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $json
            );
        }
    }

    public function test_customer_can_remove_another_device_and_revoke_its_trust(): void
    {
        $user = $this->createTotpUser();
        $old = $this->createTrustedDevice(
            $user,
            (string) Str::uuid(),
            'Old Phone'
        );

        $login = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload($user)
        )->assertOk();

        $this
            ->withToken((string) $login->json('data.access_token'))
            ->deleteJson('/api/v1/auth/devices/'.$old->uuid)
            ->assertOk()
            ->assertJsonPath('success', true);

        $old->refresh();

        $this->assertFalse((bool) $old->is_active);
        $this->assertFalse((bool) $old->is_trusted);
        $this->assertNotNull($old->revoked_at);
        $this->assertSame(
            DeviceBindingService::USER_REMOVED_REASON,
            $old->revoke_reason
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'USER_TRUSTED_DEVICE_REMOVED',
            'module' => 'SECURITY',
            'target_type' => 'USER_DEVICE',
            'target_id' => $old->getKey(),
        ]);
    }

    public function test_remove_other_device_endpoint_never_removes_current_device(): void
    {
        $user = $this->createTotpUser();

        $login = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload($user)
        )->assertOk();

        $currentUuid = (string) $login->json('data.device.uuid');

        $this
            ->withToken((string) $login->json('data.access_token'))
            ->deleteJson('/api/v1/auth/devices/'.$currentUuid)
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'CURRENT_DEVICE_NOT_ALLOWED'
            );

        $this->assertDatabaseHas('user_devices', [
            'uuid' => $currentUuid,
            'is_active' => true,
            'is_trusted' => true,
            'revoked_at' => null,
        ]);
    }

    public function test_user_removed_device_can_re_enroll_after_strong_login_when_slot_is_free(): void
    {
        $user = $this->createTotpUser();
        $oldInstallationId = (string) Str::uuid();

        $old = $this->createTrustedDevice(
            $user,
            $oldInstallationId,
            'Old Phone'
        );

        $currentLogin = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload($user)
        )->assertOk();

        $this
            ->withToken((string) $currentLogin->json('data.access_token'))
            ->deleteJson('/api/v1/auth/devices/'.$old->uuid)
            ->assertOk();

        /* Test-only reset allows another valid TOTP in the same wall-clock step. */
        $user->forceFill([
            'totp_last_used_step' => null,
        ])->save();

        $reLogin = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload(
                $user->fresh(),
                $oldInstallationId,
                'Old Phone Re-enrolled'
            )
        );

        $reLogin
            ->assertOk()
            ->assertJsonPath('data.device.uuid', $old->uuid)
            ->assertJsonPath('data.device.is_trusted', true);

        $old->refresh();

        $this->assertTrue((bool) $old->is_active);
        $this->assertTrue((bool) $old->is_trusted);
        $this->assertNull($old->revoked_at);
        $this->assertNull($old->revoke_reason);
    }

    public function test_new_trusted_device_login_creates_security_audit_event(): void
    {
        $user = $this->createTotpUser();

        $login = $this->postJson(
            '/api/v1/auth/login',
            $this->loginPayload($user)
        )->assertOk();

        $deviceUuid = (string) $login->json('data.device.uuid');
        $device = UserDevice::query()
            ->where('uuid', $deviceUuid)
            ->firstOrFail();

        $audit = AuditLog::query()
            ->where('actor_type', 'USER')
            ->where('actor_id', $user->getKey())
            ->where('action', 'USER_NEW_TRUSTED_DEVICE_ADDED')
            ->where('module', 'SECURITY')
            ->where('target_type', 'USER_DEVICE')
            ->where('target_id', $device->getKey())
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(1, (int) ($audit->new_values['active_trusted_devices'] ?? 0));
        $this->assertArrayNotHasKey('installation_id_hash', $audit->new_values ?? []);
        $this->assertArrayNotHasKey('totp_secret', $audit->new_values ?? []);
    }

    private function createTotpUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Customer Device Security User',
            'email' => 'device-security-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make('StrongPassword123!'),
            'referral_code' => 'REF'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);

        $user->forceFill([
            'totp_secret' => 'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => null,
        ])->save();

        return $user->refresh();
    }

    private function createTrustedDevice(
        User $user,
        string $installationId,
        string $name
    ): UserDevice {
        return $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => $name,
            'installation_id_hash' => hash(
                'sha256',
                strtolower(trim($installationId))
            ),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
            'last_seen_at' => now(),
            'last_login_at' => now(),
        ]);
    }

    private function loginPayload(
        User $user,
        ?string $installationId = null,
        string $deviceName = 'Current Phone'
    ): array {
        return [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'totp_code' => app(TotpService::class)->codeAt(
                (string) $user->totp_secret,
                time()
            ),
            'installation_id' => $installationId ?? (string) Str::uuid(),
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => $deviceName,
            'device_model' => 'TradeX Test Device',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
        ];
    }
}
