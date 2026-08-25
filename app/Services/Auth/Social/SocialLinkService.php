<?php

namespace App\Services\Auth\Social;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserSocialAccount;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SocialLinkService
{
    public function __construct(
        private readonly SocialProviderVerifier $providers,
        private readonly SocialChallengeService $challenges,
        private readonly TotpService $totp,
    ) {
    }

    public function link(User $authenticatedUser, string $provider, array $payload, Request $request): array
    {
        $identity = $this->providers->verify($provider, (string) $payload['credential']);
        $this->challenges->consume($provider, 'LINK', $payload['challenge_id'] ?? null, $identity->nonce, $request);

        return DB::transaction(function () use ($authenticatedUser, $identity, $provider, $payload, $request): array {
            $user = User::query()->whereKey($authenticatedUser->getKey())->lockForUpdate()->firstOrFail();
            if (! $user->canLogin()) {
                throw new AuthApiException(403, 'ACCOUNT_NOT_ACTIVE', 'This account is not allowed to access the API.');
            }

            $this->totp->consume($user, (string) $payload['totp_code']);

            $subjectOwner = UserSocialAccount::query()
                ->where('provider', $provider)
                ->where('provider_user_id', $identity->subject)
                ->lockForUpdate()
                ->first();

            if ($subjectOwner && (int) $subjectOwner->user_id !== (int) $user->getKey()) {
                throw new AuthApiException(409, 'SOCIAL_IDENTITY_ALREADY_LINKED', 'This social identity is already linked to another account.');
            }

            $current = UserSocialAccount::query()
                ->where('user_id', $user->getKey())
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();

            if ($current && ! hash_equals((string) $current->provider_user_id, $identity->subject)) {
                throw new AuthApiException(409, 'SOCIAL_PROVIDER_ALREADY_LINKED', 'A different identity from this provider is already linked.');
            }

            $social = $current ?: UserSocialAccount::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'provider_user_id' => $identity->subject,
                'provider_email' => $identity->email,
                'linked_at' => now(),
            ]);

            if ($current) {
                $social->forceFill(['provider_email' => $identity->email ?: $social->provider_email])->save();
            }

            AuditLog::record(
                'USER', $user->getKey(), 'SOCIAL_ACCOUNT_LINKED', 'AUTH',
                'USER_SOCIAL_ACCOUNT', $social->getKey(), null,
                ['provider' => $provider, 'social_account_uuid' => $social->uuid],
                $request->ip(), $request->userAgent()
            );

            return [
                'provider' => $provider,
                'linked' => true,
                'social_account_uuid' => $social->uuid,
            ];
        });
    }
}
