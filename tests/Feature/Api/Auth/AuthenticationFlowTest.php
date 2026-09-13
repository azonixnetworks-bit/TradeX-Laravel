<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
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

    public function test_second_mobile_installation_is_allowed_after_strong_login(): void
    {
        $user = $this->createTotpUser();

        $this->createTrustedDevice(
            $user,
            (string) Str::uuid(),
            UserDevice::PLATFORM_ANDROID,
            'First Phone'
        );

        $this
            ->postJson(
                '/api/v1/auth/login',
                $this->loginPayload(
                    $user
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.device.device_slot',
                UserDevice::SLOT_MOBILE
            );

        $this->assertSame(
            2,
            $user->devices()
                ->active()
                ->trusted()
                ->count()
        );
    }

    public function test_third_new_installation_is_blocked_when_trusted_device_limit_is_reached(): void
    {
        $user = $this->createTotpUser();

        $this->createTrustedDevice(
            $user,
            (string) Str::uuid(),
            UserDevice::PLATFORM_ANDROID,
            'First Phone'
        );

        $this->createTrustedDevice(
            $user,
            (string) Str::uuid(),
            UserDevice::PLATFORM_IOS,
            'Second Phone'
        );

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
                'DEVICE_LIMIT_REACHED'
            );

        $this->assertSame(
            2,
            $user->devices()
                ->active()
                ->trusted()
                ->count()
        );
    }

    public function test_known_trusted_device_can_login_again_when_device_limit_is_full(): void
    {
        $user = $this->createTotpUser();
        $knownInstallationId = (string) Str::uuid();

        $known = $this->createTrustedDevice(
            $user,
            $knownInstallationId,
            UserDevice::PLATFORM_ANDROID,
            'Known Phone'
        );

        $this->createTrustedDevice(
            $user,
            (string) Str::uuid(),
            UserDevice::PLATFORM_IOS,
            'Second Phone'
        );

        $this
            ->postJson(
                '/api/v1/auth/login',
                $this->loginPayload(
                    $user,
                    $knownInstallationId
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.device.uuid',
                $known->uuid
            );

        $this->assertSame(
            2,
            $user->devices()
                ->active()
                ->trusted()
                ->count()
        );
    }

    private function createTotpUser(): User
    {
        $user = User::query()->create([
            'uuid' =>
                (string) Str::uuid(),
            'name' =>
                'API Auth User',
            'email' =>
                'auth-'
                .Str::lower(
                    Str::random(8)
                )
                .'@example.test',
            'email_verified_at' =>
                now(),
            'password' =>
                Hash::make(
                    'StrongPassword123!'
                ),
            'referral_code' =>
                'REF'
                .Str::upper(
                    Str::random(8)
                ),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' =>
                'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' =>
                now(),
        ]);

        $user->forceFill([
            'totp_secret' =>
                'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' =>
                now(),
            'totp_last_used_step' =>
                null,
        ])->save();

        return $user->refresh();
    }

    private function createTrustedDevice(
        User $user,
        string $installationId,
        string $platform,
        string $name
    ): UserDevice {
        $slot = in_array(
            $platform,
            [
                UserDevice::PLATFORM_ANDROID,
                UserDevice::PLATFORM_IOS,
            ],
            true
        )
            ? UserDevice::SLOT_MOBILE
            : UserDevice::SLOT_DESKTOP;

        return $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => $slot,
            'platform' => $platform,
            'device_name' => $name,
            'installation_id_hash' => hash(
                'sha256',
                strtolower(trim($installationId))
            ),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
        ]);
    }

    private function loginPayload(
        User $user,
        ?string $installationId = null
    ): array {
        return [
            'email' => $user->email,
            'password' =>
                'StrongPassword123!',
            'totp_code' =>
                app(TotpService::class)
                    ->codeAt(
                        (string) $user
                            ->totp_secret,
                        time()
                    ),
            'installation_id' =>
                $installationId
                ?? (string) Str::uuid(),
            'platform' =>
                UserDevice::PLATFORM_ANDROID,
            'device_name' =>
                'TradeX Test Phone',
            'device_model' =>
                'Test Model',
            'os_version' =>
                'Android Test',
            'app_version' =>
                '1.0.0-test',
        ];
    }
}
