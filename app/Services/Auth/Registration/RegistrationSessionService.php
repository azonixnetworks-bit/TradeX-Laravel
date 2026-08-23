<?php

namespace App\Services\Auth\Registration;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRegistrationSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegistrationSessionService
{
    public function __construct(
        private readonly RegistrationOtpService
            $otpService
    ) {
    }

    public function start(
        array $data,
        Request $request
    ): array {
        $this->ensureIdentityAvailable(
            $data['email'],
            $data['whatsapp_number']
        );

        $referrer = null;

        if (
            filled(
                $data['referral_code']
                ?? null
            )
        ) {
            $referrer = User::query()
                ->where(
                    'referral_code',
                    $data['referral_code']
                )
                ->where(
                    'status',
                    'ACTIVE'
                )
                ->first();

            if (! $referrer) {
                throw new AuthApiException(
                    422,
                    'INVALID_REFERRAL_CODE',
                    'The referral code is invalid or unavailable.'
                );
            }
        }

        $plainToken =
            bin2hex(
                random_bytes(32)
            );

        $session = DB::transaction(
            function () use (
                $data,
                $request,
                $referrer,
                $plainToken
            ): UserRegistrationSession {
                UserRegistrationSession::query()
                    ->where(
                        'status',
                        UserRegistrationSession::STATUS_PENDING
                    )
                    ->where(
                        function ($query) use (
                            $data
                        ): void {
                            $query
                                ->where(
                                    'email',
                                    $data['email']
                                )
                                ->orWhere(
                                    'whatsapp_number',
                                    $data['whatsapp_number']
                                );
                        }
                    )
                    ->update([
                        'status' =>
                            UserRegistrationSession::STATUS_SUPERSEDED,
                    ]);

                $session =
                    UserRegistrationSession::query()
                        ->create([
                            'uuid' =>
                                (string) Str::uuid(),

                            'registration_token_hash' =>
                                hash(
                                    'sha256',
                                    $plainToken
                                ),

                            'name' =>
                                $data['name'],

                            'email' =>
                                $data['email'],

                            'whatsapp_number' =>
                                $data['whatsapp_number'],

                            'password_hash' =>
                                Hash::make(
                                    $data['password']
                                ),

                            'referral_code_input' =>
                                $data['referral_code']
                                ?? null,

                            'referred_by_user_id' =>
                                $referrer?->getKey(),

                            'country_code' =>
                                $data['country_code'],

                            'language_code' =>
                                $data['language_code'],

                            'status' =>
                                UserRegistrationSession::STATUS_PENDING,

                            'expires_at' =>
                                now()->addMinutes(
                                    (int) config(
                                        'tradex_registration.session_minutes',
                                        60
                                    )
                                ),

                            'start_ip' =>
                                $request->ip(),

                            'start_user_agent' =>
                                $request->userAgent(),
                        ]);

                AuditLog::record(
                    'SYSTEM',
                    null,
                    'REGISTRATION_STARTED',
                    'AUTH',
                    'USER_REGISTRATION_SESSION',
                    $session->getKey(),
                    null,
                    [
                        'country_code' =>
                            $session->country_code,

                        'language_code' =>
                            $session->language_code,

                        'has_referrer' =>
                            $session->referred_by_user_id
                            !== null,
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return $session;
            }
        );

        try {
            $this->otpService
                ->issueEmail(
                    $session,
                    $request
                );
        } catch (AuthApiException $exception) {
            $session->forceFill([
                'status' =>
                    UserRegistrationSession::STATUS_SUPERSEDED,
            ])->save();

            throw $exception;
        }

        return [
            'registration_id' =>
                $session->uuid,

            'registration_token' =>
                $plainToken,

            'expires_at' =>
                $session->expires_at
                    ->toIso8601String(),

            'next_action' =>
                'VERIFY_EMAIL_OTP',
        ];
    }

    public function resolvePending(
        string $uuid,
        string $plainToken
    ): UserRegistrationSession {
        $session =
            $this->resolveAny(
                $uuid,
                $plainToken
            );

        if (! $session->isPending()) {
            throw new AuthApiException(
                409,
                'REGISTRATION_NOT_PENDING',
                'This registration is no longer pending.'
            );
        }

        return $session;
    }

    public function resolveAny(
        string $uuid,
        string $plainToken
    ): UserRegistrationSession {
        $session =
            UserRegistrationSession::query()
                ->where(
                    'uuid',
                    $uuid
                )
                ->first();

        if (
            ! $session
            || ! hash_equals(
                (string)
                    $session->registration_token_hash,
                hash(
                    'sha256',
                    $plainToken
                )
            )
        ) {
            throw new AuthApiException(
                401,
                'INVALID_REGISTRATION_SESSION',
                'The registration session is invalid.'
            );
        }

        if (
            ! $session->isCompleted()
            && $session->isExpired()
        ) {
            if (
                $session->status
                === UserRegistrationSession::STATUS_PENDING
            ) {
                $session->forceFill([
                    'status' =>
                        UserRegistrationSession::STATUS_EXPIRED,
                ])->save();
            }

            throw new AuthApiException(
                410,
                'REGISTRATION_SESSION_EXPIRED',
                'The registration session has expired.'
            );
        }

        return $session;
    }

    public function status(
        UserRegistrationSession $session
    ): array {
        return [
            'registration_id' =>
                $session->uuid,

            'status' =>
                $session->status,

            'email_verified' =>
                $session->email_verified_at
                !== null,

            'whatsapp_verified' =>
                $session->whatsapp_verified_at
                !== null,

            'totp_setup' =>
                filled(
                    $session->totp_secret
                ),

            'totp_confirmed' =>
                $session->totp_confirmed_at
                !== null,

            'completed' =>
                $session->isCompleted(),

            'expires_at' =>
                $session->expires_at
                    ->toIso8601String(),

            'next_action' =>
                $this->nextAction(
                    $session
                ),
        ];
    }

    private function nextAction(
        UserRegistrationSession $session
    ): string {
        if ($session->isCompleted()) {
            return 'LOGIN';
        }

        if (
            $session->email_verified_at
            === null
        ) {
            return 'VERIFY_EMAIL_OTP';
        }

        if (
            $session->whatsapp_verified_at
            === null
        ) {
            return 'VERIFY_WHATSAPP_OTP';
        }

        if (
            blank(
                $session->totp_secret
            )
        ) {
            return 'SETUP_TOTP';
        }

        return 'CONFIRM_TOTP';
    }

    private function ensureIdentityAvailable(
        string $email,
        string $whatsapp
    ): void {
        if (
            User::withTrashed()
                ->where(
                    'email',
                    $email
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
                    $whatsapp
                )
                ->exists()
        ) {
            throw new AuthApiException(
                409,
                'WHATSAPP_ALREADY_REGISTERED',
                'This WhatsApp number is already registered.'
            );
        }
    }
}
