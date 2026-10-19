<?php

namespace App\Services\Security\QuickUnlock;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDeviceQuickUnlockChallenge;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\DeviceBindingService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuickUnlockAuthenticationService
{
    public function __construct(
        private readonly DeviceCredentialVerifier $credentials,
        private readonly DeviceBindingService $deviceBinding,
        private readonly AccessTokenService $accessTokens,
        private readonly RefreshTokenService $refreshTokens
    ) {}

    public function issueChallenge(
        string $credentialId,
        string $installationId
    ): array {
        $setting = UserDeviceQuickUnlockSetting::query()
            ->where('credential_id', strtolower($credentialId))
            ->where('enabled', true)
            ->whereNotNull('credential_public_key')
            ->whereHas('device', function ($query) use ($installationId): void {
                $query
                    ->where(
                        'installation_id_hash',
                        $this->deviceBinding->hashInstallationId($installationId)
                    )
                    ->where('is_active', true)
                    ->where('is_trusted', true)
                    ->whereNull('revoked_at');
            })
            ->first();

        if ($setting === null || ! $this->trustEpochMatches($setting, $setting->device)) {
            throw $this->unavailable();
        }

        $nonce = $this->base64UrlEncode(random_bytes(32));
        $expiresAt = now()->addSeconds(max(
            30,
            (int) config('tradex_mobile_security.quick_unlock.challenge_ttl_seconds', 120)
        ));

        $challenge = DB::transaction(function () use ($setting, $nonce, $expiresAt) {
            UserDeviceQuickUnlockChallenge::query()
                ->where('quick_unlock_setting_id', $setting->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return UserDeviceQuickUnlockChallenge::query()->create([
                'id' => (string) Str::uuid(),
                'quick_unlock_setting_id' => $setting->getKey(),
                'nonce_hash' => hash('sha256', $nonce),
                'expires_at' => $expiresAt,
            ]);
        }, 3);

        return [
            'challenge_id' => $challenge->getKey(),
            'credential_id' => $setting->credential_id,
            'nonce' => $nonce,
            'expires_at' => $expiresAt->toIso8601String(),
            'algorithm' => DeviceCredentialVerifier::ALGORITHM_ES256,
        ];
    }

    public function verifyChallenge(array $payload, Request $request): array
    {
        $result = DB::transaction(function () use ($payload, $request): array {
            $challenge = UserDeviceQuickUnlockChallenge::query()
                ->with(['setting.device.user'])
                ->whereKey($payload['challenge_id'])
                ->lockForUpdate()
                ->first();

            if ($challenge === null || $challenge->consumed_at !== null) {
                throw new AuthApiException(
                    401,
                    'QUICK_UNLOCK_CHALLENGE_INVALID',
                    'The quick-login challenge is invalid or was already used.'
                );
            }

            if ($challenge->expires_at->isPast()) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return ['error' => [
                    'code' => 'QUICK_UNLOCK_CHALLENGE_EXPIRED',
                    'message' => 'The quick-login challenge expired. Please try again.',
                ]];
            }

            $setting = $challenge->setting;
            $device = $setting?->device;
            $user = $device?->user;

            if (
                ! $setting instanceof UserDeviceQuickUnlockSetting
                || ! $device instanceof UserDevice
                || ! $user instanceof User
                || ! $setting->enabled
                || $setting->credential_algorithm !== DeviceCredentialVerifier::ALGORITHM_ES256
                || ! hash_equals((string) $setting->credential_id, strtolower($payload['credential_id']))
                || ! hash_equals(
                    (string) $device->installation_id_hash,
                    $this->deviceBinding->hashInstallationId($payload['installation_id'])
                )
                || $device->isRevoked()
                || ! $device->is_trusted
                || ! $user->canLogin()
                || ! $this->trustEpochMatches($setting, $device)
                || ! hash_equals($challenge->nonce_hash, hash('sha256', $payload['nonce']))
            ) {
                $challenge->forceFill(['consumed_at' => now()])->save();

                return ['error' => [
                    'code' => 'QUICK_UNLOCK_ENROLLMENT_UNAVAILABLE',
                    'message' => 'Quick login is not enrolled for this trusted device. Use email, password and authenticator code to recover access.',
                ]];
            }

            $message = $this->credentials->canonicalMessage(
                (string) $challenge->getKey(),
                (string) $setting->credential_id,
                $payload['installation_id'],
                $payload['nonce']
            );

            if (! $this->credentials->verify(
                (string) $setting->credential_public_key,
                $message,
                $payload['signature']
            )) {
                $attempts = $challenge->failed_attempts + 1;
                $challenge->forceFill([
                    'failed_attempts' => $attempts,
                    'consumed_at' => $attempts >= max(
                        1,
                        (int) config('tradex_mobile_security.quick_unlock.challenge_max_attempts', 3)
                    ) ? now() : null,
                ])->save();

                return ['error' => [
                    'code' => 'QUICK_UNLOCK_SIGNATURE_INVALID',
                    'message' => 'The device credential could not be verified.',
                ]];
            }

            $challenge->forceFill(['consumed_at' => now()])->save();

            $familyUuid = (string) Str::uuid();
            $access = $this->accessTokens->issue($user, $device, $familyUuid);
            $refresh = $this->refreshTokens->issueInitial($user, $device, $familyUuid);

            $device->forceFill([
                'last_seen_at' => now(),
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();

            AuditLog::record(
                'USER',
                $user->getKey(),
                'QUICK_UNLOCK_LOGIN_SUCCESS',
                'AUTH',
                'USER_DEVICE',
                $device->getKey(),
                null,
                [
                    'method' => $setting->method,
                    'credential_id' => $setting->credential_id,
                    'session_uuid' => $familyUuid,
                ],
                $request->ip(),
                $request->userAgent()
            );

            return [
                'access_token' => $access->plainTextToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('tradex_auth.access_token_minutes', 15) * 60,
                'refresh_token' => $refresh['plain_text_token'],
                'refresh_expires_in' => (int) config('tradex_auth.refresh_token_days', 30) * 86400,
                'session_uuid' => $familyUuid,
                'quick_unlock_method' => $setting->method,
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'referral_code' => $user->referral_code,
                    'country_code' => $user->country_code,
                    'language_code' => $user->language_code,
                    'account_type' => $user->account_type,
                    'status' => $user->status,
                ],
                'device' => [
                    'uuid' => $device->uuid,
                    'device_slot' => $device->device_slot,
                    'platform' => $device->platform,
                    'device_name' => $device->device_name,
                    'device_model' => $device->device_model,
                    'app_version' => $device->app_version,
                    'is_trusted' => (bool) $device->is_trusted,
                ],
            ];
        }, 3);

        if (isset($result['error'])) {
            throw new AuthApiException(
                401,
                $result['error']['code'],
                $result['error']['message']
            );
        }

        return $result;
    }

    private function trustEpochMatches(
        UserDeviceQuickUnlockSetting $setting,
        ?UserDevice $device
    ): bool {
        return $device !== null
            && $setting->trusted_at_snapshot !== null
            && $device->trusted_at !== null
            && $setting->trusted_at_snapshot->getTimestamp()
                === $device->trusted_at->getTimestamp();
    }

    private function unavailable(): AuthApiException
    {
        return new AuthApiException(
            401,
            'QUICK_UNLOCK_ENROLLMENT_UNAVAILABLE',
            'Quick login is not enrolled for this trusted device. Use email, password and authenticator code to recover access.'
        );
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
