<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceBindingService
{
    public function bindAfterStrongLogin(
        User $user,
        array $payload,
        Request $request
    ): UserDevice {
        $platform = strtoupper(
            (string) $payload['platform']
        );

        $slot = $this->slotForPlatform(
            $platform
        );

        $installationHash = hash(
            'sha256',
            strtolower(
                trim(
                    (string) $payload[
                        'installation_id'
                    ]
                )
            )
        );

        $existing = $user
            ->devices()
            ->where(
                'installation_id_hash',
                $installationHash
            )
            ->first();

        if (
            $existing !== null
            && $existing->isRevoked()
        ) {
            throw new AuthApiException(
                403,
                'DEVICE_REVOKED',
                'This device has been revoked and cannot be reused without a security reset.'
            );
        }

        $slotOwner = $user
            ->devices()
            ->where(
                'device_slot',
                $slot
            )
            ->where(
                'is_active',
                true
            )
            ->whereNull(
                'revoked_at'
            )
            ->first();

        if (
            $slotOwner !== null
            && (
                $existing === null
                || $slotOwner->getKey()
                    !== $existing->getKey()
            )
        ) {
            throw new AuthApiException(
                409,
                'DEVICE_SLOT_OCCUPIED',
                $slot === UserDevice::SLOT_MOBILE
                    ? 'The mobile device slot is already occupied.'
                    : 'The desktop device slot is already occupied.'
            );
        }

        $attributes = [
            'device_slot' => $slot,
            'platform' => $platform,
            'device_name' =>
                $payload['device_name']
                ?? null,
            'device_model' =>
                $payload['device_model']
                ?? null,
            'os_version' =>
                $payload['os_version']
                ?? null,
            'app_version' =>
                $payload['app_version']
                ?? null,
            'device_public_key' =>
                $payload['device_public_key']
                ?? null,
            'is_trusted' => true,
            'is_active' => true,
            'trusted_at' =>
                $existing?->trusted_at
                ?? now(),
            'last_seen_at' => now(),
            'last_login_at' => now(),
            'last_login_ip' =>
                $request->ip(),
            'revoked_at' => null,
            'revoke_reason' => null,
        ];

        if ($existing !== null) {
            $existing->forceFill(
                $attributes
            )->save();

            return $existing->refresh();
        }

        return $user
            ->devices()
            ->create([
                'uuid' =>
                    (string) Str::uuid(),
                'installation_id_hash' =>
                    $installationHash,
                'registered_at' => now(),
                ...$attributes,
            ]);
    }

    public function hashInstallationId(
        string $installationId
    ): string {
        return hash(
            'sha256',
            strtolower(
                trim($installationId)
            )
        );
    }

    private function slotForPlatform(
        string $platform
    ): string {
        return match ($platform) {
            UserDevice::PLATFORM_ANDROID,
            UserDevice::PLATFORM_IOS
                => UserDevice::SLOT_MOBILE,

            UserDevice::PLATFORM_WINDOWS,
            UserDevice::PLATFORM_MACOS,
            UserDevice::PLATFORM_WEB
                => UserDevice::SLOT_DESKTOP,

            default => throw new AuthApiException(
                422,
                'UNSUPPORTED_PLATFORM',
                'The supplied platform is not supported.'
            ),
        };
    }
}
