<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Models\AuditLog;
use App\Models\PasswordRecoveryOtpChallenge;
use App\Models\PasswordRecoverySession;
use App\Models\User;
use App\Exceptions\AuthApiException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PasswordRecoverySessionService
{
    public function __construct(
        private readonly PasswordRecoverySecurity $security,
        private readonly PasswordRecoveryOtpService $otp
    ) {
    }

    public function start(
        string $email,
        string $whatsappNumber,
        Request $request
    ): array {
        $normalizedEmail = mb_strtolower(
            trim($email)
        );

        $normalizedWhatsapp = preg_replace(
            '/[\s\-\(\)]+/',
            '',
            $whatsappNumber
        ) ?? '';

        $user = User::query()
            ->where('email', $normalizedEmail)
            ->where(
                'whatsapp_number',
                $normalizedWhatsapp
            )
            ->first();

        if (
            $user !== null
            && (
                ! $user->canLogin()
                || $user->email_verified_at === null
                || $user->whatsapp_verified_at === null
                || ! $user->hasConfirmedTotp()
            )
        ) {
            $user = null;
        }

        $accessToken = $this->security->randomToken();

        $session = DB::transaction(
            function () use (
                $user,
                $normalizedEmail,
                $normalizedWhatsapp,
                $accessToken
            ): PasswordRecoverySession {
                if ($user !== null) {
                    PasswordRecoverySession::query()
                        ->where('user_id', $user->getKey())
                        ->whereNull('consumed_at')
                        ->whereNull('invalidated_at')
                        ->where('expires_at', '>', now())
                        ->update([
                            'invalidated_at' => now(),
                        ]);
                }

                return PasswordRecoverySession::query()
                    ->create([
                        'user_id' => $user?->getKey(),
                        'access_token_hash' =>
                            $this->security->accessTokenHash(
                                $accessToken
                            ),
                        'email_hmac' =>
                            $this->security->hmac(
                                'email',
                                $normalizedEmail
                            ),
                        'whatsapp_hmac' =>
                            $this->security->hmac(
                                'whatsapp',
                                $normalizedWhatsapp
                            ),
                        'expires_at' => now()->addMinutes(
                            max(
                                5,
                                (int) config(
                                    'tradex_password_recovery.session_minutes',
                                    30
                                )
                            )
                        ),
                    ]);
            }
        );

        /*
         * First-stage delivery errors must not reveal that an account exists.
         */
        try {
            $this->otp->issue(
                $session->fresh('user'),
                PasswordRecoveryOtpChallenge::CHANNEL_EMAIL,
                false
            );
        } catch (AuthApiException) {
            if ($session->user_id !== null) {
                $session->forceFill([
                    'invalidated_at' => now(),
                ])->save();
            }
        }

        if ($user !== null) {
            AuditLog::record(
                'USER',
                $user->getKey(),
                'PASSWORD_RECOVERY_STARTED',
                'AUTH',
                'USER',
                $user->getKey(),
                null,
                [
                    'recovery_uuid' => $session->uuid,
                ],
                $request->ip(),
                $request->userAgent()
            );
        }

        return [
            'recovery_id' => $session->uuid,
            'recovery_token' => $accessToken,
            'next_action' => 'VERIFY_EMAIL_OTP',
            'message' =>
                'If the supplied account details are eligible, a verification code has been sent.',
        ];
    }

    public function resolve(
        string $uuid,
        string $accessToken,
        bool $lock = false
    ): PasswordRecoverySession {
        $query = PasswordRecoverySession::query()
            ->where('uuid', $uuid);

        if ($lock) {
            $query->lockForUpdate();
        }

        $session = $query->first();

        if (
            $session === null
            || ! $session->isUsable()
            || ! hash_equals(
                (string) $session->access_token_hash,
                $this->security->accessTokenHash(
                    $accessToken
                )
            )
        ) {
            throw new AuthApiException(
                422,
                'PASSWORD_RECOVERY_SESSION_INVALID',
                'The password recovery session is invalid or expired.'
            );
        }

        return $session;
    }

    public function markEmailVerified(
        PasswordRecoverySession $session
    ): void {
        $session->forceFill([
            'email_verified_at' =>
                $session->email_verified_at ?? now(),
        ])->save();
    }

    public function markWhatsAppVerified(
        PasswordRecoverySession $session
    ): void {
        $session->forceFill([
            'whatsapp_verified_at' =>
                $session->whatsapp_verified_at ?? now(),
        ])->save();
    }
}
