<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PasswordRecoverySession;
use Illuminate\Http\Request;

class PasswordRecoveryResetTokenService
{
    public function __construct(
        private readonly PasswordRecoverySecurity $security,
        private readonly PasswordRecoveryDeliveryService $delivery
    ) {
    }

    public function issueAndDeliver(
        PasswordRecoverySession $session,
        Request $request
    ): void {
        if (
            ! $session->isUsable()
            || ! $session->isReadyForResetLink()
            || $session->user === null
        ) {
            throw new AuthApiException(
                409,
                'PASSWORD_RECOVERY_NOT_READY',
                'Complete all password recovery verification steps first.'
            );
        }

        $token = $this->tokenValue();

        $session->forceFill([
            'reset_token_hash' =>
                $this->security->hmac(
                    'reset-token',
                    $token
                ),
            'reset_token_issued_at' => now(),
            'reset_token_expires_at' => now()->addMinutes(
                max(
                    5,
                    (int) config(
                        'tradex_password_recovery.reset_token_minutes',
                        15
                    )
                )
            ),
        ])->save();

        $this->delivery->sendResetLink(
            (string) $session->user->email,
            $session,
            $token
        );

        AuditLog::record(
            'USER',
            $session->user->getKey(),
            'PASSWORD_RECOVERY_RESET_LINK_SENT',
            'AUTH',
            'USER',
            $session->user->getKey(),
            null,
            [
                'recovery_uuid' => $session->uuid,
            ],
            $request->ip(),
            $request->userAgent()
        );
    }

    public function assertValid(
        PasswordRecoverySession $session,
        string $token
    ): void {
        if (
            $session->reset_token_hash === null
            || $session->reset_token_expires_at === null
            || $session->reset_token_expires_at->isPast()
            || ! hash_equals(
                (string) $session->reset_token_hash,
                $this->security->hmac(
                    'reset-token',
                    $token
                )
            )
        ) {
            throw new AuthApiException(
                422,
                'PASSWORD_RECOVERY_RESET_TOKEN_INVALID',
                'The password reset link is invalid or expired.'
            );
        }
    }

    private function tokenValue(): string
    {
        $fixed = config(
            'tradex_password_recovery.testing.fixed_reset_token'
        );

        if (
            app()->environment('testing')
            && is_string($fixed)
            && strlen($fixed) >= 32
        ) {
            return $fixed;
        }

        return $this->security->randomToken();
    }
}
