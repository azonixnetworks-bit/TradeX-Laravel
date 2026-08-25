<?php

namespace Tests\Concerns;

use App\Models\PasswordRecoverySession;
use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Testing\TestResponse;

trait CreatesPasswordRecoveryFlow
{
    protected string $passwordRecoveryOtp = '123456';

    protected string $passwordRecoveryResetToken =
        'Password-Recovery-Reset-Token-1234567890';

    protected function configurePasswordRecoveryTesting(): void
    {
        config([
            'tradex_password_recovery.delivery.email_driver' =>
                'null',
            'tradex_password_recovery.delivery.whatsapp_driver' =>
                'null',
            'tradex_password_recovery.testing.fixed_otp' =>
                $this->passwordRecoveryOtp,
            'tradex_password_recovery.testing.fixed_reset_token' =>
                $this->passwordRecoveryResetToken,
            'tradex_password_recovery.otp.resend_cooldown_seconds' =>
                0,
        ]);
    }

    protected function startPasswordRecovery(
        User $user
    ): TestResponse {
        return $this->postJson(
            '/api/v1/auth/password-recovery/start',
            [
                'email' => $user->email,
                'whatsapp_number' =>
                    $user->whatsapp_number,
            ]
        );
    }

    protected function completePasswordRecoveryFactors(
        User $user
    ): array {
        $start = $this
            ->startPasswordRecovery($user)
            ->assertAccepted()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_EMAIL_OTP'
            );

        $recoveryId = (string) $start->json(
            'data.recovery_id'
        );

        $recoveryToken = (string) $start->json(
            'data.recovery_token'
        );

        $base = [
            'recovery_id' => $recoveryId,
            'recovery_token' => $recoveryToken,
        ];

        $this->postJson(
            '/api/v1/auth/password-recovery/email/verify',
            [
                ...$base,
                'otp_code' =>
                    $this->passwordRecoveryOtp,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_WHATSAPP_OTP'
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/whatsapp/verify',
            [
                ...$base,
                'otp_code' =>
                    $this->passwordRecoveryOtp,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_TOTP'
            );

        $freshUser = $user->fresh();

        $totp = app(
            TotpService::class
        )->codeAt(
            (string) $freshUser->totp_secret,
            time()
        );

        $this->postJson(
            '/api/v1/auth/password-recovery/totp/verify',
            [
                ...$base,
                'totp_code' => $totp,
            ]
        )
            ->assertOk()
            ->assertJsonPath(
                'data.reset_link_sent',
                true
            )
            ->assertJsonPath(
                'data.next_action',
                'RESET_PASSWORD'
            );

        return [
            'recovery_id' => $recoveryId,
            'recovery_token' => $recoveryToken,
            'reset_token' =>
                $this->passwordRecoveryResetToken,
        ];
    }

    protected function recoverySession(
        string $uuid
    ): PasswordRecoverySession {
        return PasswordRecoverySession::query()
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
