<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthSecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_is_security_audited(): void
    {
        [
            $user,
            $payload,
        ] = $this->userAndPayload();

        $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'USER',
                'actor_id' =>
                    $user->getKey(),
                'action' =>
                    'API_LOGIN_SUCCESS',
                'module' => 'AUTH',
            ]
        );
    }

    public function test_login_endpoint_is_rate_limited(): void
    {
        [
            $user,
            $payload,
        ] = $this->userAndPayload();

        $payload['password'] =
            'WrongPassword!';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this
                ->postJson(
                    '/api/v1/auth/login',
                    $payload
                )
                ->assertUnauthorized();
        }

        $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertStatus(429);
    }

    public function test_refresh_token_cannot_move_to_another_installation(): void
    {
        [
            $user,
            $payload,
        ] = $this->userAndPayload();

        $login = $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertOk()
            ->json('data');

        $this
            ->postJson(
                '/api/v1/auth/refresh',
                [
                    'refresh_token' =>
                        $login[
                            'refresh_token'
                        ],
                    'installation_id' =>
                        (string) Str::uuid(),
                ]
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'DEVICE_BINDING_MISMATCH'
            );
    }

    public function test_logout_all_revokes_sessions_but_preserves_trusted_device(): void
    {
        [
            $user,
            $payload,
        ] = $this->userAndPayload();

        $login = $this
            ->postJson(
                '/api/v1/auth/login',
                $payload
            )
            ->assertOk()
            ->json('data');

        $this
            ->withToken(
                $login['access_token']
            )
            ->postJson(
                '/api/v1/auth/logout-all'
            )
            ->assertOk();

        $device = $user
            ->devices()
            ->firstOrFail();

        $this->assertTrue(
            (bool) $device->is_active
        );

        $this->assertTrue(
            (bool) $device->is_trusted
        );

        $this->assertNull(
            $device->revoked_at
        );

        $this
            ->withToken(
                $login['access_token']
            )
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertUnauthorized();
    }

    private function userAndPayload(): array
    {
        $user = User::query()->create([
            'uuid' =>
                (string) Str::uuid(),
            'name' =>
                'Security Test User',
            'email' =>
                'security-'
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
        ])->save();

        $installationId =
            (string) Str::uuid();

        return [
            $user,
            [
                'email' =>
                    $user->email,
                'password' =>
                    'StrongPassword123!',
                'totp_code' =>
                    app(
                        TotpService::class
                    )->codeAt(
                        (string) $user
                            ->fresh()
                            ->totp_secret,
                        time()
                    ),
                'installation_id' =>
                    $installationId,
                'platform' =>
                    UserDevice::PLATFORM_ANDROID,
                'device_name' =>
                    'Security Test Phone',
            ],
        ];
    }
}
