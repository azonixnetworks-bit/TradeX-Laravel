<?php

namespace Tests\Feature\Api\Auth;

use App\Models\PasswordRecoveryOtpChallenge;
use App\Models\UserRefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PasswordRecoverySecurityTest extends TestCase
{
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePasswordRecoveryTesting();
    }

    public function test_whatsapp_verification_cannot_run_before_email(): void
    {
        $user = $this->createApiUser();

        $start = $this
            ->startPasswordRecovery($user)
            ->assertAccepted();

        $this->postJson(
            '/api/v1/auth/password-recovery/whatsapp/verify',
            [
                'recovery_id' =>
                    $start->json('data.recovery_id'),
                'recovery_token' =>
                    $start->json('data.recovery_token'),
                'otp_code' =>
                    $this->passwordRecoveryOtp,
            ]
        )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_EMAIL_REQUIRED'
            );
    }

    public function test_invalid_recovery_token_is_rejected(): void
    {
        $user = $this->createApiUser();

        $start = $this
            ->startPasswordRecovery($user)
            ->assertAccepted();

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            [
                'recovery_id' =>
                    $start->json('data.recovery_id'),
                'recovery_token' =>
                    str_repeat('x', 43),
                'otp_code' =>
                    $this->passwordRecoveryOtp,
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_SESSION_INVALID'
            );
    }

    public function test_password_reset_revokes_tokens_and_untrusts_devices(): void
    {
        $user = $this->createApiUser();

        $this->issueBoundToken($user);

        $device = $user->devices()
            ->firstOrFail();

        UserRefreshToken::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'user_device_id' => $device->getKey(),
                'token_hash' => hash(
                    'sha256',
                    'refresh-token-test'
                ),
                'family_uuid' =>
                    (string) Str::uuid(),
                'expires_at' => now()->addDays(7),
            ]);

        $flow =
            $this->completePasswordRecoveryFactors(
                $user
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            [
                ...$flow,
                'password' =>
                    'TradeX-Revoked-Session-456!',
                'password_confirmation' =>
                    'TradeX-Revoked-Session-456!',
            ]
        )->assertOk();

        $this->assertDatabaseMissing(
            'personal_access_tokens',
            [
                'tokenable_type' => get_class($user),
                'tokenable_id' => $user->getKey(),
            ]
        );

        $refresh =
            UserRefreshToken::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->firstOrFail();

        $this->assertNotNull(
            $refresh->revoked_at
        );

        $this->assertSame(
            'PASSWORD_RESET',
            $refresh->revoke_reason
        );

        $device->refresh();

        $this->assertFalse(
            (bool) $device->is_trusted
        );
        $this->assertTrue(
            (bool) $device->is_active
        );
        $this->assertNull($device->revoked_at);
    }

    public function test_failed_otp_attempts_persist_and_invalidate_challenge(): void
    {
        config([
            'tradex_password_recovery.otp.max_attempts' => 2,
        ]);

        $user = $this->createApiUser();

        $start = $this
            ->startPasswordRecovery($user)
            ->assertAccepted();

        $payload = [
            'recovery_id' =>
                $start->json('data.recovery_id'),
            'recovery_token' =>
                $start->json('data.recovery_token'),
            'otp_code' => '000000',
        ];

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            $payload
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_OTP_INVALID'
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            $payload
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_OTP_INVALID'
            );

        $challenge =
            PasswordRecoveryOtpChallenge::query()
                ->latest('id')
                ->firstOrFail();

        $this->assertSame(
            2,
            (int) $challenge->attempts
        );

        $this->assertNotNull(
            $challenge->invalidated_at
        );

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            $payload
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_OTP_INVALID'
            );
    }
}
