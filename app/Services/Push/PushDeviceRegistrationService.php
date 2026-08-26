<?php

namespace App\Services\Push;

use App\Exceptions\PushApiException;
use App\Models\AuditLog;
use App\Models\PushDeviceToken;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PushDeviceRegistrationService
{
    public function status(User $user, Request $request): array
    {
        $device = $this->trustedMobileDevice($user, $request);
        $provider = $this->providerForPlatform($device->platform);

        $token = PushDeviceToken::query()
            ->where('user_id', $user->getKey())
            ->where('user_device_id', $device->getKey())
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->whereNull('invalidated_at')
            ->latest('id')
            ->first();

        return [
            'runtime_state' => 'WIRED_DEVICE_REGISTRATION',
            'registered' => $token instanceof PushDeviceToken,
            'provider' => $provider,
            'platform' => $device->platform,
            'device_uuid' => $device->uuid,
            'delivery_enabled' => $this->deliveryEnabled($provider),
            'registered_at' => $token?->registered_at?->toISOString(),
            'last_seen_at' => $token?->last_seen_at?->toISOString(),
        ];
    }

    public function register(
        User $user,
        Request $request,
        string $rawToken
    ): array {
        $device = $this->trustedMobileDevice($user, $request);
        $provider = $this->providerForPlatform($device->platform);
        $normalizedToken = $this->normalizeToken($provider, $rawToken);
        $tokenHash = $this->tokenHash($normalizedToken);

        [$token, $created, $changed, $reclaimed] = DB::transaction(
            function () use (
                $user,
                $device,
                $provider,
                $normalizedToken,
                $tokenHash
            ): array {
                $currentRows = PushDeviceToken::query()
                    ->where('user_device_id', $device->getKey())
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->get();

                $existing = PushDeviceToken::query()
                    ->where('token_hash', $tokenHash)
                    ->lockForUpdate()
                    ->first();

                if (
                    $existing instanceof PushDeviceToken
                    && (int) $existing->user_id === (int) $user->getKey()
                    && (int) $existing->user_device_id === (int) $device->getKey()
                    && $existing->provider === $provider
                    && $existing->isUsable()
                ) {
                    $existing->forceFill([
                        'last_seen_at' => now(),
                    ])->save();

                    return [$existing, false, false, false];
                }

                foreach ($currentRows as $current) {
                    if (
                        ! $existing instanceof PushDeviceToken
                        || (int) $current->getKey()
                            !== (int) $existing->getKey()
                    ) {
                        $current->forceFill([
                            'is_active' => false,
                            'revoked_at' => now(),
                        ])->save();
                    }
                }

                $reclaimed = $existing instanceof PushDeviceToken
                    && (
                        (int) $existing->user_id !== (int) $user->getKey()
                        || (int) $existing->user_device_id
                            !== (int) $device->getKey()
                    );

                if ($existing instanceof PushDeviceToken) {
                    $existing->forceFill([
                        'user_id' => $user->getKey(),
                        'user_device_id' => $device->getKey(),
                        'provider' => $provider,
                        'platform' => $device->platform,
                        'token_ciphertext' =>
                            Crypt::encryptString($normalizedToken),
                        'is_active' => true,
                        'registered_at' => now(),
                        'last_seen_at' => now(),
                        'revoked_at' => null,
                        'invalidated_at' => null,
                    ])->save();

                    return [$existing, false, true, $reclaimed];
                }

                $created = PushDeviceToken::query()->create([
                    'user_id' => $user->getKey(),
                    'user_device_id' => $device->getKey(),
                    'provider' => $provider,
                    'platform' => $device->platform,
                    'token_hash' => $tokenHash,
                    'token_ciphertext' =>
                        Crypt::encryptString($normalizedToken),
                    'is_active' => true,
                    'registered_at' => now(),
                    'last_seen_at' => now(),
                ]);

                return [$created, true, true, false];
            },
            3
        );

        if ($changed) {
            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: 'PUSH_DEVICE_TOKEN_REGISTERED',
                module: 'PUSH_NOTIFICATIONS',
                targetType: 'PUSH_DEVICE_TOKEN',
                targetId: $token->getKey(),
                newValues: [
                    'push_token_uuid' => $token->uuid,
                    'device_uuid' => $device->uuid,
                    'platform' => $device->platform,
                    'provider' => $provider,
                    'ownership_reassigned' => $reclaimed,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        return [
            'created' => $created,
            'registered' => true,
            'provider' => $provider,
            'platform' => $device->platform,
            'device_uuid' => $device->uuid,
            'delivery_enabled' => $this->deliveryEnabled($provider),
            'registered_at' => $token->registered_at?->toISOString(),
        ];
    }

    public function unregister(User $user, Request $request): array
    {
        $device = $this->trustedMobileDevice($user, $request);

        $updated = DB::transaction(
            fn (): int => PushDeviceToken::query()
                ->where('user_id', $user->getKey())
                ->where('user_device_id', $device->getKey())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]),
            3
        );

        if ($updated > 0) {
            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: 'PUSH_DEVICE_TOKEN_UNREGISTERED',
                module: 'PUSH_NOTIFICATIONS',
                targetType: 'USER_DEVICE',
                targetId: $device->getKey(),
                newValues: [
                    'device_uuid' => $device->uuid,
                    'revoked_token_count' => $updated,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        return [
            'registered' => false,
            'device_uuid' => $device->uuid,
            'revoked_token_count' => $updated,
        ];
    }

    private function trustedMobileDevice(
        User $user,
        Request $request
    ): UserDevice {
        $device = $request->attributes->get('tradex_user_device');

        if (
            ! $device instanceof UserDevice
            || (int) $device->user_id !== (int) $user->getKey()
            || $device->isRevoked()
            || ! $device->is_trusted
            || ! $device->isMobile()
        ) {
            throw new PushApiException(
                401,
                'TRUSTED_MOBILE_DEVICE_REQUIRED',
                'A valid trusted TradeX mobile device session is required.'
            );
        }

        return $device;
    }

    private function providerForPlatform(string $platform): string
    {
        return match ($platform) {
            UserDevice::PLATFORM_ANDROID => PushDeviceToken::PROVIDER_FCM,
            UserDevice::PLATFORM_IOS => PushDeviceToken::PROVIDER_APNS,
            default => throw new PushApiException(
                422,
                'PUSH_PLATFORM_UNSUPPORTED',
                'Push registration is supported only for Android and iOS mobile devices.'
            ),
        };
    }

    private function normalizeToken(string $provider, string $raw): string
    {
        $token = trim($raw);

        if ($provider === PushDeviceToken::PROVIDER_APNS) {
            $token = preg_replace('/[\s<>]/', '', $token) ?? '';

            if (
                strlen($token) < 32
                || strlen($token) > 4096
                || strlen($token) % 2 !== 0
                || ! ctype_xdigit($token)
            ) {
                throw new PushApiException(
                    422,
                    'INVALID_APNS_DEVICE_TOKEN',
                    'The APNs device token format is invalid.'
                );
            }

            return strtolower($token);
        }

        if (
            strlen($token) < 16
            || strlen($token) > 4096
            || preg_match('/[\x00-\x1F\x7F]/', $token) === 1
        ) {
            throw new PushApiException(
                422,
                'INVALID_FCM_REGISTRATION_TOKEN',
                'The FCM registration token format is invalid.'
            );
        }

        return $token;
    }

    private function tokenHash(string $token): string
    {
        $key = (string) config('app.key', '');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for push token hashing.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        return hash_hmac('sha256', $token, $key);
    }

    private function deliveryEnabled(string $provider): bool
    {
        if (! (bool) config('tradex_push.enabled', false)) {
            return false;
        }

        return match ($provider) {
            PushDeviceToken::PROVIDER_FCM =>
                (bool) config('tradex_push.fcm.enabled', false),
            PushDeviceToken::PROVIDER_APNS =>
                (bool) config('tradex_push.apns.enabled', false),
            default => false,
        };
    }
}
