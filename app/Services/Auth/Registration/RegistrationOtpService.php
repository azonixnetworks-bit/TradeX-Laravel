<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\UserRegistrationOtpChallenge;
use App\Models\UserRegistrationSession;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationOtpService
{
    public function __construct(
        private readonly RegistrationOtpDeliveryService
            $delivery
    ) {
    }

    public function issueEmail(
        UserRegistrationSession $session,
        Request $request
    ): void {
        $this->issue(
            $session,
            UserRegistrationOtpChallenge::CHANNEL_EMAIL,
            $request
        );
    }

    public function issueWhatsApp(
        UserRegistrationSession $session,
        Request $request
    ): void {
        if (
            $session->email_verified_at
            === null
        ) {
            throw new AuthApiException(
                409,
                'EMAIL_VERIFICATION_REQUIRED',
                'Verify the email address before WhatsApp verification.'
            );
        }

        $this->issue(
            $session,
            UserRegistrationOtpChallenge::CHANNEL_WHATSAPP,
            $request
        );
    }

    public function ensureWhatsAppIssued(
        UserRegistrationSession $session,
        Request $request
    ): void {
        $activeExists =
            UserRegistrationOtpChallenge::query()
                ->where(
                    'user_registration_session_id',
                    $session->getKey()
                )
                ->where(
                    'channel',
                    UserRegistrationOtpChallenge::CHANNEL_WHATSAPP
                )
                ->whereNull(
                    'consumed_at'
                )
                ->whereNull(
                    'invalidated_at'
                )
                ->where(
                    'expires_at',
                    '>',
                    now()
                )
                ->exists();

        if (! $activeExists) {
            $this->issueWhatsApp(
                $session,
                $request
            );
        }
    }

    public function verifyEmail(
        UserRegistrationSession $session,
        string $code,
        Request $request
    ): void {
        if (
            $session->email_verified_at
            !== null
        ) {
            return;
        }

        $this->verify(
            $session,
            UserRegistrationOtpChallenge::CHANNEL_EMAIL,
            $code,
            $request
        );

        $session->forceFill([
            'email_verified_at' =>
                now(),
        ])->save();

        AuditLog::record(
            'SYSTEM',
            null,
            'REGISTRATION_EMAIL_VERIFIED',
            'AUTH',
            'USER_REGISTRATION_SESSION',
            $session->getKey(),
            null,
            null,
            $request->ip(),
            $request->userAgent()
        );
    }

    public function verifyWhatsApp(
        UserRegistrationSession $session,
        string $code,
        Request $request
    ): void {
        if (
            $session->email_verified_at
            === null
        ) {
            throw new AuthApiException(
                409,
                'EMAIL_VERIFICATION_REQUIRED',
                'Verify the email address first.'
            );
        }

        if (
            $session->whatsapp_verified_at
            !== null
        ) {
            return;
        }

        $this->verify(
            $session,
            UserRegistrationOtpChallenge::CHANNEL_WHATSAPP,
            $code,
            $request
        );

        $session->forceFill([
            'whatsapp_verified_at' =>
                now(),
        ])->save();

        AuditLog::record(
            'SYSTEM',
            null,
            'REGISTRATION_WHATSAPP_VERIFIED',
            'AUTH',
            'USER_REGISTRATION_SESSION',
            $session->getKey(),
            null,
            null,
            $request->ip(),
            $request->userAgent()
        );
    }

    private function issue(
        UserRegistrationSession $session,
        string $channel,
        Request $request
    ): void {
        if (! $session->isPending()) {
            throw new AuthApiException(
                409,
                'REGISTRATION_NOT_PENDING',
                'This registration is no longer pending.'
            );
        }

        $latest =
            UserRegistrationOtpChallenge::query()
                ->where(
                    'user_registration_session_id',
                    $session->getKey()
                )
                ->where(
                    'channel',
                    $channel
                )
                ->latest('id')
                ->first();

        $cooldown = (int) config(
            'tradex_registration.otp.resend_cooldown_seconds',
            60
        );

        if (
            $latest
            && $latest->sent_at
            && $latest->sent_at
                ->addSeconds(
                    $cooldown
                )
                ->isFuture()
        ) {
            throw new AuthApiException(
                429,
                'OTP_RESEND_COOLDOWN',
                'Please wait before requesting another verification code.'
            );
        }

        $code =
            $this->generateCode(
                $channel
            );

        $challenge = DB::transaction(
            function () use (
                $session,
                $channel,
                $code
            ): UserRegistrationOtpChallenge {
                UserRegistrationOtpChallenge::query()
                    ->where(
                        'user_registration_session_id',
                        $session->getKey()
                    )
                    ->where(
                        'channel',
                        $channel
                    )
                    ->whereNull(
                        'consumed_at'
                    )
                    ->whereNull(
                        'invalidated_at'
                    )
                    ->update([
                        'invalidated_at' =>
                            now(),
                    ]);

                return UserRegistrationOtpChallenge::query()
                    ->create([
                        'user_registration_session_id' =>
                            $session->getKey(),

                        'channel' =>
                            $channel,

                        'code_hmac' =>
                            $this->hmac(
                                $code
                            ),

                        'attempts' =>
                            0,

                        'sent_at' =>
                            now(),

                        'expires_at' =>
                            now()->addMinutes(
                                (int) config(
                                    'tradex_registration.otp.expires_minutes',
                                    10
                                )
                            ),
                    ]);
            }
        );

        try {
            if (
                $channel
                === UserRegistrationOtpChallenge::CHANNEL_EMAIL
            ) {
                $this->delivery->sendEmail(
                    $session->email,
                    $code
                );
            } else {
                $this->delivery->sendWhatsApp(
                    $session->whatsapp_number,
                    $code
                );
            }
        } catch (AuthApiException $exception) {
            $challenge->forceFill([
                'invalidated_at' =>
                    now(),
            ])->save();

            throw $exception;
        }

        AuditLog::record(
            'SYSTEM',
            null,
            'REGISTRATION_OTP_SENT',
            'AUTH',
            'USER_REGISTRATION_SESSION',
            $session->getKey(),
            null,
            [
                'channel' =>
                    $channel,
            ],
            $request->ip(),
            $request->userAgent()
        );
    }

    private function verify(
        UserRegistrationSession $session,
        string $channel,
        string $code,
        Request $request
    ): void {
        $challenge =
            UserRegistrationOtpChallenge::query()
                ->where(
                    'user_registration_session_id',
                    $session->getKey()
                )
                ->where(
                    'channel',
                    $channel
                )
                ->whereNull(
                    'consumed_at'
                )
                ->whereNull(
                    'invalidated_at'
                )
                ->latest('id')
                ->first();

        if (! $challenge) {
            throw new AuthApiException(
                422,
                'OTP_NOT_FOUND',
                'No active verification code was found.'
            );
        }

        if (
            $challenge->expires_at
                ->isPast()
        ) {
            $challenge->forceFill([
                'invalidated_at' =>
                    now(),
            ])->save();

            throw new AuthApiException(
                422,
                'OTP_EXPIRED',
                'The verification code has expired.'
            );
        }

        $challenge->increment(
            'attempts'
        );

        $challenge->refresh();

        $maxAttempts = (int) config(
            'tradex_registration.otp.max_attempts',
            5
        );

        if (
            $challenge->attempts
            > $maxAttempts
        ) {
            $challenge->forceFill([
                'invalidated_at' =>
                    now(),
            ])->save();

            throw new AuthApiException(
                429,
                'OTP_ATTEMPTS_EXCEEDED',
                'Too many verification attempts. Request a new code.'
            );
        }

        if (
            ! hash_equals(
                (string)
                    $challenge->code_hmac,
                $this->hmac(
                    $code
                )
            )
        ) {
            $attemptsExceeded =
                $challenge->attempts
                >= $maxAttempts;

            if ($attemptsExceeded) {
                $challenge->forceFill([
                    'invalidated_at' =>
                        now(),
                ])->save();
            }

            AuditLog::record(
                'SYSTEM',
                null,
                'REGISTRATION_OTP_FAILED',
                'AUTH',
                'USER_REGISTRATION_SESSION',
                $session->getKey(),
                null,
                [
                    'channel' =>
                        $channel,

                    'attempt' =>
                        $challenge->attempts,
                ],
                $request->ip(),
                $request->userAgent()
            );

            if ($attemptsExceeded) {
                throw new AuthApiException(
                    429,
                    'OTP_ATTEMPTS_EXCEEDED',
                    'Too many verification attempts. Request a new code.'
                );
            }

            throw new AuthApiException(
                422,
                'INVALID_OTP',
                'The verification code is invalid.'
            );
        }

        $challenge->forceFill([
            'consumed_at' =>
                now(),
        ])->save();
    }

    private function generateCode(
        string $channel
    ): string {
        if (
            $channel
            === UserRegistrationOtpChallenge::CHANNEL_EMAIL
        ) {
            $fixedEmail = config(
                'tradex_registration.testing.fixed_email_otp'
            );

            if (
                app()->environment('testing')
                && is_string($fixedEmail)
                && preg_match('/^\d{8}$/', $fixedEmail)
            ) {
                return $fixedEmail;
            }

            $legacyFixed = config(
                'tradex_registration.testing.fixed_otp'
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

        $fixed =
            config(
                'tradex_registration.testing.fixed_otp'
            );

        if (
            app()->environment('testing')
            && filled($fixed)
        ) {
            return (string) $fixed;
        }

        $digits = (int) config(
            'tradex_registration.otp.digits',
            6
        );

        $minimum =
            10 ** ($digits - 1);

        $maximum =
            (10 ** $digits) - 1;

        return (string) random_int(
            $minimum,
            $maximum
        );
    }

    private function hmac(
        string $code
    ): string {
        return hash_hmac(
            'sha256',
            $code,
            (string) config('app.key')
        );
    }
}
