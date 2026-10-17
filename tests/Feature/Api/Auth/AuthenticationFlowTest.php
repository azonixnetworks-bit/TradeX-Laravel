<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_password_totp_and_device_receive_tokens(): void
    {
        $user = $this->createTotpUser();

        $payload =
            $this->loginPayload(
                $user
            );

        $response = $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.token_type',
                'Bearer'
            )
            ->assertJsonPath(
                'data.user.uuid',
                $user->uuid
            )
            ->assertJsonPath(
                'data.device.device_slot',
                UserDevice::SLOT_MOBILE
            );

        $this->assertNotEmpty(
            $response->json(
                'data.access_token'
            )
        );

        $this->assertNotEmpty(
            $response->json(
                'data.refresh_token'
            )
        );

        $this
            ->withToken(
                $response->json(
                    'data.access_token'
                )
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertOk();
    }

    public function test_invalid_totp_is_rejected(): void
    {
        $user = $this->createTotpUser();

        $payload =
            $this->loginPayload(
                $user
            );

        $payload['totp_code'] =
            $payload['totp_code']
                === '000000'
                ? '999999'
                : '000000';

        $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_TOTP'
            );
    }

    public function test_strong_login_can_enroll_biometric_quick_unlock(): void
    {
        $user = $this->createTotpUser();
        $payload = $this->loginPayload($user);
        $payload['quick_unlock_method'] = 'BIOMETRIC';

        $response = $this->postJson('/api/v1/auth/login', $payload);

        $response
            ->assertOk()
            ->assertJsonPath('data.quick_unlock_method', 'BIOMETRIC');

        $deviceUuid = $response->json('data.device.uuid');
        $device = UserDevice::query()->where('uuid', $deviceUuid)->firstOrFail();

        $this->assertDatabaseHas('user_device_quick_unlock_settings', [
            'user_device_id' => $device->getKey(),
            'enabled' => true,
            'method' => UserDeviceQuickUnlockSetting::METHOD_BIOMETRIC,
        ]);
    }

    public function test_same_totp_step_cannot_be_replayed(): void
    {
        $user = $this->createTotpUser();

        $payload =
            $this->loginPayload(
                $user
            );

        $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertOk();

        $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'TOTP_REPLAYED'
            );
    }

    public function test_second_mobile_installation_is_blocked_when_mobile_slot_is_occupied(): void
    {
        $user = $this->createTotpUser();

        $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'installation_id_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);

        $this
            ->postJson(
                '/api/v1/auth/login',
                $this->loginPayload(
                    $user
                )
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'DEVICE_SLOT_OCCUPIED'
            );
    }

    private function createTotpUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'API Auth User',
            'email' => 'auth-'
                .Str::lower(
                    Str::random(8)
                )
                .'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make(
                'StrongPassword123!'
            ),
            'referral_code' => 'REF'
                .Str::upper(
                    Str::random(8)
                ),
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

    private function loginPayload(
        User $user
    ): array {
        return [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'totp_code' => app(TotpService::class)
                ->codeAt(
                    (string) $user
                        ->totp_secret,
                    time()
                ),
            'installation_id' => (string) Str::uuid(),
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'TradeX Test Phone',
            'device_model' => 'Test Model',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
        ];
    }
}
