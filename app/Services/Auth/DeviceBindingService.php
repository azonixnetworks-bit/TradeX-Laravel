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

        $installationHash = $this->hashInstallationId(
            (string) $payload['installation_id']
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

        /*
         * Existing trusted devices are allowed to sign in again even when the
         * account already has the maximum number of trusted devices. Only a
         * genuinely new installation consumes another device position.
         *
         * LoginService holds a row lock on the user before this method runs,
         * so concurrent logins for the same account cannot both bypass this
         * count and create more than the configured limit.
         */
        if ($existing === null) {
            $maxTrustedDevices = max(
                1,
                (int) config(
                    'tradex_auth.max_trusted_devices',
                    2
                )
            );

            $activeTrustedDevices = $user
                ->devices()
                ->where('is_active', true)
                ->where('is_trusted', true)
                ->whereNull('revoked_at')
                ->count();

            if ($activeTrustedDevices >= $maxTrustedDevices) {
                throw new AuthApiException(
                    409,
                    'DEVICE_LIMIT_REACHED',
                    "This account already has {$maxTrustedDevices} trusted devices. Sign out or remove an existing device before adding another one."
                );
            }
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
