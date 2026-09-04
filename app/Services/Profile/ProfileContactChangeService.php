<?php

namespace App\Services\Profile;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\UserProfileChangeChallenge;
use App\Services\Auth\TotpService;
use App\Support\Security\BrandedEmailOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileContactChangeService
{
    public function __construct(
        private readonly TotpService
            $totpService,
        private readonly ProfileOtpDeliveryService
            $delivery
    ) {
    }

    public function requestWhatsApp(
        User $user,
        string $newNumber,
        string $totpCode,
        Request $request
    ): array {
        if (
            $newNumber
            === $user->whatsapp_number
        ) {
            throw new AuthApiException(
                409,
                'WHATSAPP_UNCHANGED',
                'The new WhatsApp number is the same as the current number.'
            );
        }

        if (
            User::withTrashed()
                ->where(
                    'whatsapp_number',
                    $newNumber
                )
                ->where(
                    'id',
                    '!=',
                    $user->getKey()
                )
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'WHATSAPP_ALREADY_REGISTERED',
                'This WhatsApp number is already registered.'
            );
        }

        return $this->requestChange(
            $user,
            UserProfileChangeChallenge::PURPOSE_WHATSAPP,
            $newNumber,
            $totpCode,
            $request
        );
    }

    public function requestTelegram(
        User $user,
        string $newUsername,
        string $totpCode,
        Request $request
    ): array {
        $current =
            $user->profile
                ?->telegram_username;

        if ($current === $newUsername) {
            throw new AuthApiException(
                409,
                'TELEGRAM_UNCHANGED',
                'The new Telegram username is the same as the current username.'
            );
        }

        return $this->requestChange(
            $user,
            UserProfileChangeChallenge::PURPOSE_TELEGRAM,
            $newUsername,
            $totpCode,
            $request
        );
    }

    public function confirmWhatsApp(
        User $user,
        array $data,
        Request $request
    ): void {
        $this->confirm(
            $user,
            UserProfileChangeChallenge::PURPOSE_WHATSAPP,
            $data,
            $request
        );
    }

    public function confirmTelegram(
        User $user,
        array $data,
        Request $request
    ): void {
        $this->confirm(
            $user,
            UserProfileChangeChallenge::PURPOSE_TELEGRAM,
            $data,
            $request
        );
    }

    private function requestChange(
        User $user,
        string $purpose,
        string $newValue,
        string $totpCode,
        Request $request
    ): array {
        if (
            $user->email_verified_at
            === null
        ) {
            throw new AuthApiException(
                409,
                'VERIFIED_EMAIL_REQUIRED',
                'A verified email address is required for contact changes.'
            );
        }

        $plainToken =
            bin2hex(
                random_bytes(32)
            );

        $otp =
            $this->generateOtp();

        $challenge =
            DB::transaction(
                function () use (
                    $user,
                    $purpose,
                    $newValue,
                    $totpCode,
                    $request,
                    $plainToken,
                    $otp
                ): UserProfileChangeChallenge {
                    $this->totpService
                        ->consume(
                            $user,
                            $totpCode
                        );

                    UserProfileChangeChallenge::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->where(
                            'purpose',
                            $purpose
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

                    return UserProfileChangeChallenge::query()
                        ->create([
                            'uuid' =>
                                (string) Str::uuid(),

                            'user_id' =>
                                $user->getKey(),

                            'purpose' =>
                                $purpose,

                            'challenge_token_hash' =>
                                hash(
                                    'sha256',
                                    $plainToken
                                ),

                            'new_value' =>
                                $newValue,

                            'otp_hmac' =>
                                $this->hmac(
                                    $otp
                                ),

                            'attempts' =>
                                0,

                            'expires_at' =>
                                now()->addMinutes(
                                    (int) config(
                                        'tradex_profile.contact_change.expires_minutes',
                                        10
                                    )
                                ),

                            'request_ip' =>
                                $request->ip(),

                            'request_user_agent' =>
                                $request->userAgent(),
                        ]);
                }
            );

        try {
            $this->delivery->sendEmail(
                $user->email,
                $otp,
                $purpose
            );
        } catch (AuthApiException $exception) {
            $challenge->forceFill([
                'invalidated_at' =>
                    now(),
            ])->save();

            throw $exception;
        }

        AuditLog::record(
            'USER',
            $user->getKey(),
            'PROFILE_CONTACT_CHANGE_REQUESTED',
            'PROFILE',
            'USER_PROFILE_CHANGE_CHALLENGE',
            $challenge->getKey(),
            null,
            [
                'purpose' =>
                    $purpose,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return [
            'challenge_id' =>
                $challenge->uuid,

            'challenge_token' =>
                $plainToken,

            'expires_at' =>
                $challenge
                    ->expires_at
                    ->toIso8601String(),

            'verification_channel' =>
                'EMAIL',

            'next_action' =>
                'CONFIRM_EMAIL_OTP',
        ];
    }

    private function confirm(
        User $user,
        string $expectedPurpose,
        array $data,
        Request $request
    ): void {
        $challenge =
            UserProfileChangeChallenge::query()
                ->where(
                    'uuid',
                    $data['challenge_id']
                )
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->first();

        $this->validateChallengeIdentity(
            $challenge,
            $expectedPurpose,
            $data['challenge_token']
        );

        if (
            $challenge
                ->expires_at
                ->isPast()
        ) {
            $challenge->forceFill([
                'invalidated_at' =>
                    now(),
            ])->save();

            throw new AuthApiException(
                410,
                'PROFILE_CHANGE_CHALLENGE_EXPIRED',
                'The profile-change challenge has expired.'
            );
        }

        $challenge->increment(
            'attempts'
        );

        $challenge->refresh();

        $maxAttempts =
            (int) config(
                'tradex_profile.contact_change.max_attempts',
                5
            );

        if (
            ! hash_equals(
                (string)
                    $challenge
                        ->otp_hmac,
                $this->hmac(
                    $data['otp_code']
                )
            )
        ) {
            $exceeded =
                $challenge->attempts
                >= $maxAttempts;

            if ($exceeded) {
                $challenge->forceFill([
                    'invalidated_at' =>
                        now(),
                ])->save();
            }

            AuditLog::record(
                'USER',
                $user->getKey(),
                'PROFILE_CONTACT_CHANGE_OTP_FAILED',
                'PROFILE',
                'USER_PROFILE_CHANGE_CHALLENGE',
                $challenge->getKey(),
                null,
                [
                    'purpose' =>
                        $expectedPurpose,

                    'attempt' =>
                        $challenge->attempts,
                ],
                $request->ip(),
                $request->userAgent()
            );

            throw new AuthApiException(
                $exceeded
                    ? 429
                    : 422,
                $exceeded
                    ? 'PROFILE_CHANGE_ATTEMPTS_EXCEEDED'
                    : 'INVALID_PROFILE_CHANGE_OTP',
                $exceeded
                    ? 'Too many verification attempts. Start the change again.'
                    : 'The verification code is invalid.'
            );
        }

        DB::transaction(
            function () use (
                $user,
                $expectedPurpose,
                $data,
                $request,
                $challenge
            ): void {
                $locked =
                    UserProfileChangeChallenge::query()
                        ->whereKey(
                            $challenge->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->validateChallengeIdentity(
                    $locked,
                    $expectedPurpose,
                    $data['challenge_token']
                );

                if (
                    $locked
                        ->expires_at
                        ->isPast()
                ) {
                    throw new AuthApiException(
                        410,
                        'PROFILE_CHANGE_CHALLENGE_EXPIRED',
                        'The profile-change challenge has expired.'
                    );
                }

                if (
                    $expectedPurpose
                    === UserProfileChangeChallenge::PURPOSE_WHATSAPP
                ) {
                    $newNumber =
                        (string)
                            $locked->new_value;

                    if (
                        User::withTrashed()
                            ->where(
                                'whatsapp_number',
                                $newNumber
                            )
                            ->where(
                                'id',
                                '!=',
                                $user->getKey()
                            )
                            ->exists()
                    ) {
                        throw new AuthApiException(
                            409,
                            'WHATSAPP_ALREADY_REGISTERED',
                            'This WhatsApp number is already registered.'
                        );
                    }

                    $user->forceFill([
                        'whatsapp_number' =>
                            $newNumber,

                        'whatsapp_verified_at' =>
                            now(),
                    ])->save();
                } else {
                    $profile =
                        UserProfile::query()
                            ->firstOrNew([
                                'user_id' =>
                                    $user->getKey(),
                            ]);

                    $profile->forceFill([
                        'telegram_username' =>
                            (string)
                                $locked
                                    ->new_value,
                    ])->save();
                }

                $locked->forceFill([
                    'consumed_at' =>
                        now(),
                ])->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'PROFILE_CONTACT_CHANGE_CONFIRMED',
                    'PROFILE',
                    'USER',
                    $user->getKey(),
                    null,
                    [
                        'purpose' =>
                            $expectedPurpose,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
            }
        );
    }

    private function validateChallengeIdentity(
        ?UserProfileChangeChallenge $challenge,
        string $expectedPurpose,
        string $plainToken
    ): void {
        if (
            ! $challenge
            || ! hash_equals(
                (string)
                    $challenge
                        ->challenge_token_hash,
                hash(
                    'sha256',
                    $plainToken
                )
            )
        ) {
            throw new AuthApiException(
                401,
                'INVALID_PROFILE_CHANGE_CHALLENGE',
                'The profile-change challenge is invalid.'
            );
        }

        if (
            $challenge->purpose
            !== $expectedPurpose
        ) {
            throw new AuthApiException(
                409,
                'PROFILE_CHANGE_PURPOSE_MISMATCH',
                'The profile-change challenge does not match this action.'
            );
        }

        if (
            $challenge->consumed_at
            !== null
            || $challenge->invalidated_at
            !== null
        ) {
            throw new AuthApiException(
                409,
                'PROFILE_CHANGE_CHALLENGE_USED',
                'This profile-change challenge is no longer usable.'
            );
        }
    }

    private function generateOtp(): string
    {
        $fixedEmail =
            config(
                'tradex_profile.testing.fixed_email_otp'
            );

        if (
            app()->environment('testing')
            && is_string($fixedEmail)
            && preg_match('/^\d{8}$/', $fixedEmail)
        ) {
            return $fixedEmail;
        }

        $legacyFixed =
            config(
                'tradex_profile.testing.fixed_otp'
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

    private function hmac(
        string $value
    ): string {
        return hash_hmac(
            'sha256',
            $value,
            (string) config('app.key')
        );
    }
}
