<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserMfaRecoveryCode;
use App\Services\Auth\MfaRecoveryCodeService;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class MfaRecoveryCodeLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_auth.recovery_codes.pepper' =>
                'admin-20c-test-recovery-pepper',
            'tradex_auth.recovery_codes.count' => 10,
        ]);
    }

    public function test_recovery_code_can_login_once_and_cannot_be_replayed(): void
    {
        $user = $this->createTotpUser();
        $codes = $this->issueBootstrapCodes($user);

        $this->assertCount(10, $codes);
        $this->assertDatabaseCount(
            'user_mfa_recovery_codes',
            10
        );

        foreach ($codes as $plainCode) {
            $this->assertDatabaseMissing(
                'user_mfa_recovery_codes',
                ['code_hash' => $plainCode]
            );
        }

        $payload = $this->recoveryLoginPayload(
            $user,
            $codes[0]
        );

        $login = $this->postJson(
            '/api/v1/auth/login',
            $payload
        )
            ->assertOk()
            ->assertJsonPath(
                'data.mfa_method',
                'RECOVERY_CODE'
            );

        $this->assertNotEmpty(
            $login->json('data.access_token')
        );

        $used = UserMfaRecoveryCode::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('used_at')
            ->first();

        $this->assertNotNull($used);
        $this->assertSame(
            9,
            UserMfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->count()
        );

        $this->postJson(
            '/api/v1/auth/login',
            $payload
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_MFA_RECOVERY_CODE'
            );
    }

    public function test_authenticated_regeneration_revokes_old_unused_codes_and_returns_new_codes_once(): void
    {
        $user = $this->createTotpUser();
        $oldCodes = $this->issueBootstrapCodes($user);

        $login = $this->postJson(
            '/api/v1/auth/login',
            $this->recoveryLoginPayload(
                $user,
                $oldCodes[0]
            )
        )->assertOk();

        $totpCode = app(TotpService::class)
            ->codeAt(
                (string) $user->refresh()->totp_secret,
                time()
            );

        $regenerate = $this
            ->withToken(
                $login->json('data.access_token')
            )
            ->postJson(
                '/api/v1/auth/recovery-codes/regenerate',
                [
                    'password' => 'StrongPassword123!',
                    'totp_code' => $totpCode,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.recovery_codes_count',
                10
            )
            ->assertJsonPath(
                'data.displayed_once',
                true
            );

        $newCodes = $regenerate->json(
            'data.recovery_codes'
        );

        $this->assertCount(10, $newCodes);
        $this->assertNotSame(
            $oldCodes[1],
            $newCodes[0]
        );

        $this->assertSame(
            9,
            UserMfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNotNull('revoked_at')
                ->count()
        );

        $this->assertSame(
            10,
            UserMfaRecoveryCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->count()
        );

        $this->postJson(
            '/api/v1/auth/login',
            $this->recoveryLoginPayload(
                $user,
                $oldCodes[1]
            )
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_MFA_RECOVERY_CODE'
            );
    }

    public function test_login_requires_exactly_one_mfa_factor(): void
    {
        $user = $this->createTotpUser();

        $payload = $this->baseLoginPayload($user);

        $this->postJson(
            '/api/v1/auth/login',
            $payload
        )->assertStatus(422);

        $payload['totp_code'] = '123456';
        $payload['recovery_code'] = 'TXR-TEST';

        $this->postJson(
            '/api/v1/auth/login',
            $payload
        )->assertStatus(422);
    }

    private function issueBootstrapCodes(
        User $user
    ): array {
        $request = Request::create(
            '/tests/admin-20c/recovery-codes',
            'POST',
            server: [
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_USER_AGENT' =>
                    'TradeX ADMIN-20C Test',
            ]
        );

        return app(MfaRecoveryCodeService::class)
            ->issueBootstrap(
                $user,
                $request
            );
    }

    private function createTotpUser(): User
    {
        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'MFA Recovery Test User',
            'email' => 'mfa-recovery-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => Hash::make(
                'StrongPassword123!'
            ),
            'referral_code' =>
                'REF'.Str::upper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'FREE',
            'status' => 'ACTIVE',
        ]);

        $user->forceFill([
            'totp_secret' =>
                'JBSWY3DPEHPK3PXP',
            'totp_confirmed_at' => now(),
            'totp_last_used_step' => null,
        ])->save();

        return $user->refresh();
    }

    private function recoveryLoginPayload(
        User $user,
        string $recoveryCode
    ): array {
        return array_merge(
            $this->baseLoginPayload($user),
            [
                'recovery_code' => $recoveryCode,
            ]
        );
    }

    private function baseLoginPayload(
        User $user
    ): array {
        return [
            'email' => $user->email,
            'password' => 'StrongPassword123!',
            'installation_id' => (string) Str::uuid(),
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'Recovery Test Phone',
            'device_model' => 'Test Model',
            'os_version' => 'Android Test',
            'app_version' => '1.0.0-test',
        ];
    }
}
