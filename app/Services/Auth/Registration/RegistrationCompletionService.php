<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRegistrationSession;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegistrationCompletionService
{
    public function __construct(
        private readonly TotpService
            $totpService
    ) {
    }

    public function complete(
        UserRegistrationSession $session,
        string $totpCode,
        Request $request
    ): array {
        if ($session->isCompleted()) {
            $user =
                $session->completedUser;

            return [
                'completed' => true,
                'user_uuid' =>
                    $user?->uuid,
                'next_action' =>
                    'LOGIN',
            ];
        }

        if ($session->isAdminInvite()) {
            throw new AuthApiException(
                409,
                'INVITE_ONBOARDING_CONFIRM_REQUIRED',
                'Complete TOTP confirmation and legal acceptance through the secure invite onboarding flow.'
            );
        }

        if (
            $session->email_verified_at
            === null
            || $session->whatsapp_verified_at
            === null
            || blank(
                $session->totp_secret
            )
        ) {
            throw new AuthApiException(
                409,
                'REGISTRATION_STEPS_INCOMPLETE',
                'Complete email, WhatsApp, and TOTP setup first.'
            );
        }

        $step =
            $this->totpService
                ->matchingStep(
                    (string)
                        $session->totp_secret,
                    $totpCode
                );

        if ($step === null) {
            throw new AuthApiException(
                422,
                'INVALID_TOTP',
                'The TOTP code is invalid or expired.'
            );
        }

        return DB::transaction(
            function () use (
                $session,
                $step,
                $request
            ): array {
                $locked =
                    UserRegistrationSession::query()
                        ->whereKey(
                            $session->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($locked->isCompleted()) {
                    return [
                        'completed' =>
                            true,

                        'user_uuid' =>
                            $locked
                                ->completedUser
                                ?->uuid,

                        'next_action' =>
                            'LOGIN',
                    ];
                }

                if (
                    User::withTrashed()
                        ->where(
                            'email',
                            $locked->email
                        )
                        ->exists()
                ) {
                    throw new AuthApiException(
                        409,
                        'EMAIL_ALREADY_REGISTERED',
                        'This email address is already registered.'
                    );
                }

                if (
                    User::withTrashed()
                        ->where(
                            'whatsapp_number',
                            $locked->whatsapp_number
                        )
                        ->exists()
                ) {
                    throw new AuthApiException(
                        409,
                        'WHATSAPP_ALREADY_REGISTERED',
                        'This WhatsApp number is already registered.'
                    );
                }

                $user =
                    new User([
                        'uuid' =>
                            (string) Str::uuid(),

                        'name' =>
                            $locked->name,

                        'email' =>
                            $locked->email,

                        'email_verified_at' =>
                            $locked->email_verified_at,

                        'whatsapp_number' =>
                            $locked->whatsapp_number,

                        'whatsapp_verified_at' =>
                            $locked->whatsapp_verified_at,

                        'password' =>
                            $locked->password_hash,

                        'referral_code' =>
                            $this->generateReferralCode(),

                        'referred_by_user_id' =>
                            $locked->referred_by_user_id,

                        'country_code' =>
                            $locked->country_code,

                        'language_code' =>
                            $locked->language_code,

                        'account_type' =>
                            'FREE',

                        'status' =>
                            'ACTIVE',
                    ]);

                $user->forceFill([
                    'totp_secret' =>
                        $locked->totp_secret,

                    'totp_confirmed_at' =>
                        now(),

                    'totp_last_used_step' =>
                        $step,
                ]);

                $user->save();

                $locked->forceFill([
                    'totp_confirmed_at' =>
                        now(),

                    'totp_last_used_step' =>
                        $step,

                    'status' =>
                        UserRegistrationSession::STATUS_COMPLETED,

                    'completed_user_id' =>
                        $user->getKey(),

                    'completed_at' =>
                        now(),
                ])->save();

                AuditLog::record(
                    'USER',
                    $user->getKey(),
                    'REGISTRATION_COMPLETED',
                    'AUTH',
                    'USER',
                    $user->getKey(),
                    null,
                    [
                        'account_type' =>
                            'FREE',

                        'status' =>
                            'ACTIVE',

                        'referred_by_user_id' =>
                            $user->referred_by_user_id,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return [
                    'completed' =>
                        true,

                    'user_uuid' =>
                        $user->uuid,

                    'referral_code' =>
                        $user->referral_code,

                    'next_action' =>
                        'LOGIN',
                ];
            }
        );
    }

    private function generateReferralCode(): string
    {
        do {
            $code =
                'TX'
                .Str::upper(
                    Str::random(10)
                );
        } while (
            User::withTrashed()
                ->where(
                    'referral_code',
                    $code
                )
                ->exists()
        );

        return $code;
    }
}
