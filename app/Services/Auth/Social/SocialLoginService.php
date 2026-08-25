<?php

namespace App\Services\Auth\Social;

use App\Exceptions\AuthApiException;
use App\Models\User;
use App\Models\UserSocialAccount;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SocialLoginService
{
    public function __construct(
        private readonly SocialProviderVerifier $providers,
        private readonly SocialChallengeService $challenges,
        private readonly TotpService $totp,
        private readonly SocialSessionService $sessions,
    ) {
    }

    public function login(string $provider, array $payload, Request $request): array
    {
        $identity = $this->providers->verify($provider, (string) $payload['credential']);
        $this->challenges->consume($provider, 'LOGIN', $payload['challenge_id'] ?? null, $identity->nonce, $request);

        $candidate = UserSocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $identity->subject)
            ->first();

        if (! $candidate) {
            throw new AuthApiException(
                409,
                'SOCIAL_ACCOUNT_NOT_LINKED',
                'This social identity is not linked to a TradeX account.'
            );
        }

        return DB::transaction(function () use ($candidate, $identity, $payload, $request, $provider): array {
            $social = UserSocialAccount::query()
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $user = User::query()->whereKey($social->user_id)->lockForUpdate()->firstOrFail();

            if (! $user->canLogin()) {
                throw new AuthApiException(403, 'ACCOUNT_NOT_ACTIVE', 'This account is not allowed to login.');
            }

            $this->totp->consume($user, (string) $payload['totp_code']);

            $social->forceFill([
                'provider_email' => $identity->email ?: $social->provider_email,
                'last_login_at' => now(),
            ])->save();

            return $this->sessions->issue(
                $user,
                $payload,
                $request,
                'API_SOCIAL_LOGIN_SUCCESS',
                ['provider' => $provider, 'social_account_uuid' => $social->uuid]
            );
        });
    }
}
