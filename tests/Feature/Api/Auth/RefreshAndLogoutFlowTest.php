<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class RefreshAndLogoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_token_rotates_and_old_token_reuse_revokes_family(): void
    {
        [
            $user,
            $installationId,
            $login,
        ] = $this->loginUser();

        $oldRefresh =
            $login['refresh_token'];

        $rotated = $this
            ->postJson(
                '/api/v1/auth/refresh',
                [
                    'refresh_token' =>
                        $oldRefresh,
                    'installation_id' =>
                        $installationId,
                ]
            )
            ->assertOk()
            ->json('data');

        $this->assertNotSame(
            $oldRefresh,
            $rotated['refresh_token']
        );

        $this
            ->postJson(
                '/api/v1/auth/refresh',
                [
                    'refresh_token' =>
                        $oldRefresh,
                    'installation_id' =>
                        $installationId,
                ]
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'REFRESH_TOKEN_REUSED'
            );

        $this
            ->postJson(
                '/api/v1/auth/refresh',
                [
                    'refresh_token' =>
                        $rotated[
                            'refresh_token'
                        ],
                    'installation_id' =>
                        $installationId,
                ]
            )
            ->assertUnauthorized();
    }

    public function test_logout_deletes_current_access_session(): void
    {
        [
            $user,
            $installationId,
            $login,
        ] = $this->loginUser();

        $access =
            $login['access_token'];

        $this
            ->withToken($access)
            ->postJson(
                '/api/v1/auth/logout'
            )
            ->assertOk();

        $this
            ->withToken($access)
            ->getJson(
                '/api/v1/auth/me'
            )
            ->assertUnauthorized();
    }

    public function test_current_device_can_be_self_revoked(): void
    {
        [
            $user,
            $installationId,
            $login,
        ] = $this->loginUser();

        $this
            ->withToken(
                $login['access_token']
            )
            ->postJson(
                '/api/v1/auth/device/revoke'
            )
            ->assertOk();

        $this->assertDatabaseHas(
            'user_devices',
            [
                'user_id' =>
                    $user->getKey(),
                'is_active' => false,
            ]
        );
    }

    private function loginUser(): array
    {
        $user = User::query()->create([
            'uuid' =>
                (string) Str::uuid(),
            'name' =>
                'Refresh User',
            'email' =>
                'refresh-'
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

        $login = $this
            ->postJson(
                '/api/v1/auth/login',
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
                        'Refresh Test Phone',
                ]
            )
            ->assertOk()
            ->json('data');

        return [
            $user,
            $installationId,
            $login,
        ];
    }
}
