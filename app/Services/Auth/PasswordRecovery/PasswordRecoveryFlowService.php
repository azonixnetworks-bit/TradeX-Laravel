<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PasswordRecoveryOtpChallenge;
use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PasswordRecoveryFlowService
{
    public function __construct(
        private readonly PasswordRecoverySessionService $sessions,
        private readonly PasswordRecoveryOtpService $otp,
        private readonly PasswordRecoveryResetTokenService $resetTokens,
        private readonly TotpService $totp
    ) {
    }

    public function verifyEmail(
        string $recoveryId,
        string $recoveryToken,
        string $code,
        Request $request
    ): array {
        $session = $this->sessions->resolve(
            $recoveryId,
            $recoveryToken
        );

        if ($session->email_verified_at === null) {
            $this->otp->verify(
                $session,
                PasswordRecoveryOtpChallenge::CHANNEL_EMAIL,
                $code
            );

            $this->sessions->markEmailVerified(
                $session
            );
        }

        $session->refresh();

        if ($session->whatsapp_verified_at === null) {
            $this->otp->issue(
                $session->fresh('user'),
                PasswordRecoveryOtpChallenge::CHANNEL_WHATSAPP,
                false
            );
        }

        $this->audit(
            $session->user,
            'PASSWORD_RECOVERY_EMAIL_VERIFIED',
            $session->uuid,
            $request
        );

        return [
            'email_verified' => true,
            'next_action' => 'VERIFY_WHATSAPP_OTP',
        ];
    }

    public function resendEmail(
        string $recoveryId,
        string $recoveryToken
    ): array {
        $session = $this->sessions->resolve(
            $recoveryId,
            $recoveryToken
        );

        if ($session->email_verified_at !== null) {
            return [
                'sent' => false,
                'next_action' => 'VERIFY_WHATSAPP_OTP',
            ];
        }

        $this->otp->issue(
            $session->fresh('user'),
            PasswordRecoveryOtpChallenge::CHANNEL_EMAIL,
            true
        );

        return [
            'sent' => true,
            'next_action' => 'VERIFY_EMAIL_OTP',
        ];
    }

    public function verifyWhatsApp(
        string $recoveryId,
        string $recoveryToken,
        string $code,
        Request $request
    ): array {
        $session = $this->sessions->resolve(
            $recoveryId,
            $recoveryToken
        );

        if ($session->email_verified_at === null) {
            throw new AuthApiException(
                409,
                'PASSWORD_RECOVERY_EMAIL_REQUIRED',
                'Email verification must be completed first.'
            );
        }

        if ($session->whatsapp_verified_at === null) {
            $this->otp->verify(
                $session,
                PasswordRecoveryOtpChallenge::CHANNEL_WHATSAPP,
                $code
            );

            $this->sessions->markWhatsAppVerified(
                $session
            );
        }

        $this->audit(
            $session->user,
            'PASSWORD_RECOVERY_WHATSAPP_VERIFIED',
            $session->uuid,
            $request
        );

        return [
            'whatsapp_verified' => true,
            'next_action' => 'VERIFY_TOTP',
        ];
    }

    public function resendWhatsApp(
        string $recoveryId,
        string $recoveryToken
    ): array {
        $session = $this->sessions->resolve(
            $recoveryId,
            $recoveryToken
        );

        if ($session->email_verified_at === null) {
            throw new AuthApiException(
                409,
                'PASSWORD_RECOVERY_EMAIL_REQUIRED',
                'Email verification must be completed first.'
            );
        }

        if ($session->whatsapp_verified_at !== null) {
            return [
                'sent' => false,
                'next_action' => 'VERIFY_TOTP',
            ];
        }

        $this->otp->issue(
            $session->fresh('user'),
            PasswordRecoveryOtpChallenge::CHANNEL_WHATSAPP,
            true
        );

        return [
            'sent' => true,
            'next_action' => 'VERIFY_WHATSAPP_OTP',
        ];
    }

    public function verifyTotp(
        string $recoveryId,
        string $recoveryToken,
        string $code,
        Request $request
    ): array {
        $session = DB::transaction(function () use (
            $recoveryId,
            $recoveryToken,
            $code
        ) {
            $session = $this->sessions->resolve(
                $recoveryId,
                $recoveryToken,
                true
            );

            if (
                ! $session->hasVerifiedContacts()
                || $session->user_id === null
            ) {
                throw new AuthApiException(
                    409,
                    'PASSWORD_RECOVERY_CONTACTS_REQUIRED',
                    'Email and WhatsApp verification must be completed first.'
                );
            }

            if ($session->totp_verified_at !== null) {
                return $session;
            }

            $user = User::query()
                ->whereKey($session->user_id)
                ->lockForUpdate()
                ->first();

            if (
                $user === null
                || ! $user->canLogin()
            ) {
                throw new AuthApiException(
                    422,
                    'PASSWORD_RECOVERY_SESSION_INVALID',
                    'The password recovery session is invalid or expired.'
                );
            }

            $this->totp->consume(
                $user,
                $code
            );

            $session->forceFill([
                'totp_verified_at' => now(),
            ])->save();

            return $session;
        }, 3);

        $this->resetTokens->issueAndDeliver(
            $session->fresh('user'),
            $request
        );

        $this->audit(
            $session->user,
            'PASSWORD_RECOVERY_TOTP_VERIFIED',
            $session->uuid,
            $request
        );

        return [
            'totp_verified' => true,
            'reset_link_sent' => true,
            'next_action' => 'RESET_PASSWORD',
        ];
    }

    public function resendResetLink(
        string $recoveryId,
        string $recoveryToken,
        Request $request
    ): array {
        $session = $this->sessions->resolve(
            $recoveryId,
            $recoveryToken
        );

        $this->resetTokens->issueAndDeliver(
            $session->fresh('user'),
            $request
        );

        return [
            'reset_link_sent' => true,
            'next_action' => 'RESET_PASSWORD',
        ];
    }

    private function audit(
        ?User $user,
        string $action,
        string $recoveryUuid,
        Request $request
    ): void {
        if ($user === null) {
            return;
        }

        AuditLog::record(
            'USER',
            $user->getKey(),
            $action,
            'AUTH',
            'USER',
            $user->getKey(),
            null,
            [
                'recovery_uuid' => $recoveryUuid,
            ],
            $request->ip(),
            $request->userAgent()
        );
    }
}
