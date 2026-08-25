<?php

namespace App\Services\Auth\Social;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserRegistrationSession;
use App\Models\UserSocialAccount;
use App\Services\Auth\Registration\RegistrationOtpService;
use App\Services\Auth\Registration\RegistrationSessionService;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SocialOnboardingService
{
    public function __construct(
        private readonly SocialProviderVerifier $providers,
        private readonly SocialChallengeService $challenges,
        private readonly RegistrationOtpService $otp,
        private readonly RegistrationSessionService $registrations,
        private readonly TotpService $totp,
        private readonly SocialSessionService $sessions,
    ) {
    }

    public function start(string $provider, array $payload, Request $request): array
    {
        $identity = $this->providers->verify($provider, (string) $payload['credential']);
        $this->challenges->consume($provider, 'ONBOARD', $payload['challenge_id'] ?? null, $identity->nonce, $request);

        if (UserSocialAccount::query()->where('provider', $provider)->where('provider_user_id', $identity->subject)->exists()) {
            throw new AuthApiException(409, 'SOCIAL_ACCOUNT_ALREADY_LINKED', 'This social identity is already linked. Use social login.');
        }

        $email = $identity->email ?: ($payload['email'] ?? null);
        if (blank($email)) {
            throw new AuthApiException(422, 'SOCIAL_EMAIL_REQUIRED', 'An email address is required to complete onboarding.');
        }
        $email = mb_strtolower(trim((string) $email));

        if ($identity->email && isset($payload['email']) && $payload['email'] && ! hash_equals($email, mb_strtolower((string) $payload['email']))) {
            throw new AuthApiException(422, 'SOCIAL_EMAIL_MISMATCH', 'The supplied email does not match the social provider identity.');
        }

        $name = trim((string) ($payload['name'] ?? $identity->name ?? ''));
        if (mb_strlen($name) < 2) {
            throw new AuthApiException(422, 'SOCIAL_NAME_REQUIRED', 'A valid name is required to complete onboarding.');
        }

        $this->ensureIdentityAvailable($email, (string) $payload['whatsapp_number']);
        $referrer = $this->resolveReferrer($payload['referral_code'] ?? null);
        $plainToken = bin2hex(random_bytes(32));

        $session = DB::transaction(function () use ($provider, $identity, $email, $name, $payload, $request, $referrer, $plainToken): UserRegistrationSession {
            UserRegistrationSession::query()
                ->where('status', UserRegistrationSession::STATUS_PENDING)
                ->where(function ($query) use ($provider, $identity, $email, $payload): void {
                    $query->where('email', $email)
                        ->orWhere('whatsapp_number', $payload['whatsapp_number'])
                        ->orWhere(function ($social) use ($provider, $identity): void {
                            $social->where('social_provider', $provider)
                                ->where('social_provider_user_id', $identity->subject);
                        });
                })->update(['status' => UserRegistrationSession::STATUS_SUPERSEDED]);

            $session = UserRegistrationSession::query()->create([
                'uuid' => (string) Str::uuid(),
                'registration_token_hash' => hash('sha256', $plainToken),
                'name' => $name,
                'email' => $email,
                'whatsapp_number' => $payload['whatsapp_number'],
                'password_hash' => Hash::make(bin2hex(random_bytes(32))),
                'referral_code_input' => $payload['referral_code'] ?? null,
                'referred_by_user_id' => $referrer?->getKey(),
                'country_code' => $payload['country_code'],
                'language_code' => $payload['language_code'],
                'status' => UserRegistrationSession::STATUS_PENDING,
                'expires_at' => now()->addMinutes((int) config('tradex_registration.session_minutes', 60)),
                'start_ip' => $request->ip(),
                'start_user_agent' => $request->userAgent(),
            ]);

            $session->forceFill([
                'social_provider' => $provider,
                'social_provider_user_id' => $identity->subject,
                'social_provider_email' => $identity->email,
            ])->save();

            AuditLog::record(
                'SYSTEM', null, 'SOCIAL_REGISTRATION_STARTED', 'AUTH',
                'USER_REGISTRATION_SESSION', $session->getKey(), null,
                ['provider' => $provider, 'has_referrer' => $session->referred_by_user_id !== null],
                $request->ip(), $request->userAgent()
            );

            return $session;
        });

        try {
            // Preserve the frozen registration security gate: every social onboarding still verifies email OTP.
            $this->otp->issueEmail($session, $request);
        } catch (AuthApiException $exception) {
            $session->forceFill(['status' => UserRegistrationSession::STATUS_SUPERSEDED])->save();
            throw $exception;
        }

        return [
            'registration_id' => $session->uuid,
            'registration_token' => $plainToken,
            'expires_at' => $session->expires_at->toIso8601String(),
            'next_action' => 'VERIFY_EMAIL_OTP',
        ];
    }

    public function confirm(string $provider, array $payload, Request $request): array
    {
        $identity = $this->providers->verify($provider, (string) $payload['credential']);
        $this->challenges->consume($provider, 'ONBOARD_CONFIRM', $payload['challenge_id'] ?? null, $identity->nonce, $request);

        $session = $this->registrations->resolvePending(
            (string) $payload['registration_id'],
            (string) $payload['registration_token']
        );

        if (
            $session->social_provider !== $provider
            || blank($session->social_provider_user_id)
            || ! hash_equals((string) $session->social_provider_user_id, $identity->subject)
        ) {
            throw new AuthApiException(401, 'SOCIAL_IDENTITY_MISMATCH', 'The social identity does not match this registration.');
        }

        if ($session->email_verified_at === null || $session->whatsapp_verified_at === null || blank($session->totp_secret)) {
            throw new AuthApiException(409, 'REGISTRATION_SECURITY_STEPS_REQUIRED', 'Email, WhatsApp, and TOTP setup must be completed first.');
        }

        $step = $this->totp->matchingStep((string) $session->totp_secret, (string) $payload['totp_code']);
        if ($step === null) {
            throw new AuthApiException(422, 'INVALID_TOTP', 'The TOTP code is invalid or expired.');
        }

        return DB::transaction(function () use ($session, $identity, $provider, $payload, $request, $step): array {
            $locked = UserRegistrationSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->isPending() || $locked->isExpired()) {
                throw new AuthApiException(409, 'REGISTRATION_NOT_PENDING', 'This registration is no longer pending.');
            }

            if (User::withTrashed()->where('email', $locked->email)->exists()) {
                throw new AuthApiException(409, 'EMAIL_ALREADY_REGISTERED', 'This email address is already registered.');
            }
            if (User::withTrashed()->where('whatsapp_number', $locked->whatsapp_number)->exists()) {
                throw new AuthApiException(409, 'WHATSAPP_ALREADY_REGISTERED', 'This WhatsApp number is already registered.');
            }
            if (UserSocialAccount::query()->where('provider', $provider)->where('provider_user_id', $identity->subject)->exists()) {
                throw new AuthApiException(409, 'SOCIAL_IDENTITY_ALREADY_LINKED', 'This social identity is already linked.');
            }

            $user = User::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $locked->name,
                'email' => $locked->email,
                'email_verified_at' => $locked->email_verified_at,
                'whatsapp_number' => $locked->whatsapp_number,
                'whatsapp_verified_at' => $locked->whatsapp_verified_at,
                'password' => $locked->password_hash,
                'referral_code' => $this->generateReferralCode(),
                'referred_by_user_id' => $locked->referred_by_user_id,
                'country_code' => $locked->country_code,
                'language_code' => $locked->language_code,
                'account_type' => 'FREE',
                'status' => 'ACTIVE',
            ]);

            $user->forceFill([
                'totp_secret' => $locked->totp_secret,
                'totp_confirmed_at' => now(),
                'totp_last_used_step' => $step,
            ])->save();

            $social = UserSocialAccount::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $identity->subject,
                'provider_email' => $identity->email ?: $locked->social_provider_email,
                'linked_at' => now(),
                'last_login_at' => now(),
            ]);

            $locked->forceFill([
                'status' => UserRegistrationSession::STATUS_COMPLETED,
                'completed_user_id' => $user->getKey(),
                'completed_at' => now(),
                'totp_confirmed_at' => now(),
                'totp_last_used_step' => $step,
            ])->save();

            AuditLog::record(
                'USER', $user->getKey(), 'SOCIAL_REGISTRATION_COMPLETED', 'AUTH',
                'USER_SOCIAL_ACCOUNT', $social->getKey(), null,
                ['provider' => $provider, 'social_account_uuid' => $social->uuid],
                $request->ip(), $request->userAgent()
            );

            return $this->sessions->issue(
                $user, $payload, $request, 'API_SOCIAL_ONBOARDING_SUCCESS',
                ['provider' => $provider, 'social_account_uuid' => $social->uuid]
            );
        });
    }

    private function ensureIdentityAvailable(string $email, string $whatsapp): void
    {
        if (User::withTrashed()->where('email', $email)->exists()) {
            throw new AuthApiException(409, 'EMAIL_ALREADY_REGISTERED', 'This email address is already registered. Sign in normally, then explicitly link the social account.');
        }
        if (User::withTrashed()->where('whatsapp_number', $whatsapp)->exists()) {
            throw new AuthApiException(409, 'WHATSAPP_ALREADY_REGISTERED', 'This WhatsApp number is already registered.');
        }
    }

    private function resolveReferrer(?string $code): ?User
    {
        if (blank($code)) { return null; }
        $user = User::query()->where('referral_code', $code)->where('status', 'ACTIVE')->first();
        if (! $user) {
            throw new AuthApiException(422, 'INVALID_REFERRAL_CODE', 'The referral code is invalid or unavailable.');
        }
        return $user;
    }

    private function generateReferralCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = 'TX'.Str::upper(Str::random(10));
            if (! User::withTrashed()->where('referral_code', $code)->exists()) { return $code; }
        }
        throw new AuthApiException(503, 'REFERRAL_CODE_GENERATION_FAILED', 'A referral code could not be generated.');
    }
}
