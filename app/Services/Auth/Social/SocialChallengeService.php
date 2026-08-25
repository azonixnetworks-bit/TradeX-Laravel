<?php

namespace App\Services\Auth\Social;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\UserSocialAuthChallenge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialChallengeService
{
    public function issue(string $provider, string $purpose, Request $request): array
    {
        $this->ensureProviderRequiresNonce($provider);

        $nonce = $this->base64UrlEncode(random_bytes(32));
        $challenge = UserSocialAuthChallenge::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => $provider,
            'purpose' => $purpose,
            'nonce_hash' => hash('sha256', $nonce),
            'expires_at' => now()->addMinutes((int) config('tradex_social_auth.challenge_minutes', 5)),
            'request_ip' => $request->ip(),
        ]);

        AuditLog::record(
            'SYSTEM',
            null,
            'SOCIAL_AUTH_CHALLENGE_ISSUED',
            'AUTH',
            'USER_SOCIAL_AUTH_CHALLENGE',
            $challenge->getKey(),
            null,
            ['provider' => $provider, 'purpose' => $purpose],
            $request->ip(),
            $request->userAgent()
        );

        return [
            'challenge_id' => $challenge->uuid,
            'nonce' => $nonce,
            'expires_at' => $challenge->expires_at->toIso8601String(),
        ];
    }

    public function consume(
        string $provider,
        string $purpose,
        ?string $challengeUuid,
        ?string $tokenNonce,
        Request $request
    ): void {
        $requiresNonce = (bool) config("tradex_social_auth.providers.{$provider}.require_nonce", false);

        if (! $requiresNonce) {
            return;
        }

        if (blank($challengeUuid) || blank($tokenNonce)) {
            throw new AuthApiException(
                401,
                'SOCIAL_NONCE_REQUIRED',
                'A valid social authentication challenge is required.'
            );
        }

        DB::transaction(function () use ($provider, $purpose, $challengeUuid, $tokenNonce, $request): void {
            $challenge = UserSocialAuthChallenge::query()
                ->where('uuid', $challengeUuid)
                ->lockForUpdate()
                ->first();

            if (
                ! $challenge
                || $challenge->provider !== $provider
                || $challenge->purpose !== $purpose
                || $challenge->consumed_at !== null
                || $challenge->expires_at->isPast()
                || ! hash_equals((string) $challenge->nonce_hash, hash('sha256', $tokenNonce))
            ) {
                throw new AuthApiException(
                    401,
                    'INVALID_SOCIAL_CHALLENGE',
                    'The social authentication challenge is invalid or expired.'
                );
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            AuditLog::record(
                'SYSTEM',
                null,
                'SOCIAL_AUTH_CHALLENGE_CONSUMED',
                'AUTH',
                'USER_SOCIAL_AUTH_CHALLENGE',
                $challenge->getKey(),
                null,
                ['provider' => $provider, 'purpose' => $purpose],
                $request->ip(),
                $request->userAgent()
            );
        });
    }

    private function ensureProviderRequiresNonce(string $provider): void
    {
        if (! (bool) config("tradex_social_auth.providers.{$provider}.enabled", false)) {
            throw new AuthApiException(
                503,
                'SOCIAL_PROVIDER_NOT_CONFIGURED',
                'The social provider is not available.'
            );
        }

        if (! (bool) config("tradex_social_auth.providers.{$provider}.require_nonce", false)) {
            throw new AuthApiException(
                422,
                'SOCIAL_CHALLENGE_NOT_REQUIRED',
                'This social provider does not require a server nonce challenge.'
            );
        }
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
