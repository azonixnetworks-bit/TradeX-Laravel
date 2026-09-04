<?php

namespace App\Services\Auth\PasswordRecovery;

use App\Exceptions\AuthApiException;
use App\Models\PasswordRecoveryOtpChallenge;
use App\Models\PasswordRecoverySession;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Support\Facades\DB;

class PasswordRecoveryOtpService
{
    public function __construct(
        private readonly PasswordRecoverySecurity $security,
        private readonly PasswordRecoveryDeliveryService $delivery
    ) {
    }

    public function issue(
        PasswordRecoverySession $session,
        string $channel,
        bool $enforceCooldown = true
    ): void {
        if (
            ! in_array(
                $channel,
                PasswordRecoveryOtpChallenge::CHANNELS,
                true
            )
        ) {
            throw new AuthApiException(
                422,
                'PASSWORD_RECOVERY_CHANNEL_INVALID',
                'The password recovery channel is invalid.'
            );
        }

        if (! $session->isUsable()) {
            throw new AuthApiException(
                422,
                'PASSWORD_RECOVERY_SESSION_INVALID',
                'The password recovery session is invalid or expired.'
            );
        }

        $latest = PasswordRecoveryOtpChallenge::query()
            ->where(
                'password_recovery_session_id',
                $session->getKey()
            )
            ->where('channel', $channel)
            ->latest('id')
            ->first();

        $cooldown = max(
            0,
            (int) config(
                'tradex_password_recovery.otp.resend_cooldown_seconds',
                60
            )
        );

        if (
            $enforceCooldown
            && $latest !== null
            && $latest->sent_at !== null
            && $latest->sent_at->gt(
                now()->subSeconds($cooldown)
            )
        ) {
            throw new AuthApiException(
                429,
                'PASSWORD_RECOVERY_OTP_COOLDOWN',
                'Please wait before requesting another verification code.'
            );
        }

        $code = $this->generateCode(
            $channel
        );

        DB::transaction(function () use ($session, $channel, $code): void {
            PasswordRecoveryOtpChallenge::query()
                ->where(
                    'password_recovery_session_id',
                    $session->getKey()
                )
                ->where('channel', $channel)
                ->whereNull('consumed_at')
                ->whereNull('invalidated_at')
                ->update([
                    'invalidated_at' => now(),
                ]);

            PasswordRecoveryOtpChallenge::query()
                ->create([
                    'password_recovery_session_id' =>
                        $session->getKey(),
                    'channel' => $channel,
                    'code_hmac' => $this->codeHmac(
                        $session,
                        $channel,
                        $code
                    ),
                    'attempts' => 0,
                    'sent_at' => now(),
                    'expires_at' => now()->addMinutes(
                        max(
                            1,
                            (int) config(
                                'tradex_password_recovery.otp.expires_minutes',
                                10
                            )
                        )
                    ),
                ]);
        });

        $user = $session->user;

        /*
         * Decoy sessions deliberately create a challenge but send nothing.
         * The start response remains the same for valid and invalid identities.
         */
        if ($user === null) {
            return;
        }

        if ($channel === PasswordRecoveryOtpChallenge::CHANNEL_EMAIL) {
            $this->delivery->sendEmailOtp(
                (string) $user->email,
                $code
            );

            return;
        }

        $this->delivery->sendWhatsAppOtp(
            (string) $user->whatsapp_number,
            $code
        );
    }

    public function verify(
        PasswordRecoverySession $session,
        string $channel,
        string $code
    ): void {
        $maxAttempts = max(
            1,
            (int) config(
                'tradex_password_recovery.otp.max_attempts',
                5
            )
        );

        /*
         * Return a result from the transaction and throw afterwards.
         * This ensures failed-attempt counters / invalidation are committed
         * instead of being rolled back with the API exception.
         */
        $result = DB::transaction(function () use (
            $session,
            $channel,
            $code,
            $maxAttempts
        ): string {
            $challenge = PasswordRecoveryOtpChallenge::query()
                ->where(
                    'password_recovery_session_id',
                    $session->getKey()
                )
                ->where('channel', $channel)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (
                $session->user_id === null
                || $challenge === null
                || ! $challenge->isUsable()
            ) {
                return 'INVALID';
            }

            if ((int) $challenge->attempts >= $maxAttempts) {
                $challenge->forceFill([
                    'invalidated_at' =>
                        $challenge->invalidated_at ?? now(),
                ])->save();

                return 'EXCEEDED';
            }

            $challenge->increment('attempts');
            $challenge->refresh();

            $expected = $this->codeHmac(
                $session,
                $channel,
                $code
            );

            if (! hash_equals(
                (string) $challenge->code_hmac,
                $expected
            )) {
                if ((int) $challenge->attempts >= $maxAttempts) {
                    $challenge->forceFill([
                        'invalidated_at' => now(),
                    ])->save();
                }

                return 'INVALID';
            }

            $challenge->forceFill([
                'consumed_at' => now(),
            ])->save();

            return 'OK';
        });

        if ($result === 'EXCEEDED') {
            throw new AuthApiException(
                429,
                'PASSWORD_RECOVERY_OTP_ATTEMPTS_EXCEEDED',
                'Too many verification attempts were made.'
            );
        }

        if ($result !== 'OK') {
            throw new AuthApiException(
                422,
                'PASSWORD_RECOVERY_OTP_INVALID',
                'The verification code is invalid or expired.'
            );
        }
    }

    private function codeHmac(
        PasswordRecoverySession $session,
        string $channel,
        string $code
    ): string {
        return $this->security->hmac(
            'otp',
            $session->uuid.'|'.$channel.'|'.$code
        );
    }

    private function generateCode(
        string $channel
    ): string {
        if (
            $channel
            === PasswordRecoveryOtpChallenge::CHANNEL_EMAIL
        ) {
            $fixedEmail = config(
                'tradex_password_recovery.testing.fixed_email_otp'
            );

            if (
                app()->environment('testing')
                && is_string($fixedEmail)
                && preg_match('/^\d{8}$/', $fixedEmail)
            ) {
                return $fixedEmail;
            }

            $legacyFixed = config(
                'tradex_password_recovery.testing.fixed_otp'
            );

            if (
                app()->environment('testing')
                && is_string($legacyFixed)
                && preg_match('/^\d{6}$/', $legacyFixed)
            ) {
                return $legacyFixed;
            }

            return BrandedEmailOtp::generate();
        }

        $digits = max(
            4,
            min(
                8,
                (int) config(
                    'tradex_password_recovery.otp.digits',
                    6
                )
            )
        );

        $fixed = config(
            'tradex_password_recovery.testing.fixed_otp'
        );

        if (
            app()->environment('testing')
            && is_string($fixed)
            && preg_match('/^\d{'.$digits.'}$/', $fixed)
        ) {
            return $fixed;
        }

        $maximum = (10 ** $digits) - 1;

        return str_pad(
            (string) random_int(0, $maximum),
            $digits,
            '0',
            STR_PAD_LEFT
        );
    }
}
