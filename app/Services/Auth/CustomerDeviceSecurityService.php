<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerDeviceSecurityService
{
    public function __construct(
        private readonly AccessTokenService $accessTokens
    ) {
    }

    public function activeTrustedDevices(
        User $user,
        UserDevice $currentDevice
    ): Collection {
        return $user->devices()
            ->active()
            ->trusted()
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get()
            ->map(
                fn (UserDevice $device): array =>
                    $this->safeDevice(
                        $device,
                        $device->getKey() === $currentDevice->getKey()
                    )
            );
    }

    public function removeOtherDevice(
        User $user,
        UserDevice $currentDevice,
        string $deviceUuid,
        Request $request
    ): void {
        $target = $user->devices()
            ->where('uuid', $deviceUuid)
            ->first();

        if (! $target instanceof UserDevice) {
            throw new AuthApiException(
                404,
                'DEVICE_NOT_FOUND',
                'The selected device was not found.'
            );
        }

        if ($target->getKey() === $currentDevice->getKey()) {
            throw new AuthApiException(
                409,
                'CURRENT_DEVICE_NOT_ALLOWED',
                'Use the current-device security action to revoke this device.'
            );
        }

        if (! $target->is_active || $target->revoked_at !== null) {
            throw new AuthApiException(
                409,
                'DEVICE_ALREADY_INACTIVE',
                'The selected device is already inactive.'
            );
        }

        DB::transaction(function () use ($user, $target, $request): void {
            $target->forceFill([
                'is_trusted' => false,
                'is_active' => false,
                'revoked_at' => now(),
                'revoked_by_admin_id' => null,
                'revoke_reason' => DeviceBindingService::USER_REMOVED_REASON,
            ])->save();

            $refreshCount = UserRefreshToken::query()
                ->where('user_id', $user->getKey())
                ->where('user_device_id', $target->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'USER_DEVICE_REMOVED',
                ]);

            $accessCount = $this->accessTokens->revokeDevice(
                $user,
                $target
            );

            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: 'USER_TRUSTED_DEVICE_REMOVED',
                module: 'SECURITY',
                targetType: 'USER_DEVICE',
                targetId: $target->getKey(),
                newValues: [
                    'device_uuid' => $target->uuid,
                    'platform' => $target->platform,
                    'device_name' => $target->device_name,
                    'refresh_tokens_revoked' => $refreshCount,
                    'access_tokens_revoked' => $accessCount,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });
    }

    public function maxTrustedDevices(): int
    {
        return max(
            1,
            (int) config('tradex_auth.max_trusted_devices', 2)
        );
    }

    private function safeDevice(
        UserDevice $device,
        bool $isCurrent
    ): array {
        return [
            'uuid' => $device->uuid,
            'device_slot' => $device->device_slot,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
            'device_model' => $device->device_model,
            'os_version' => $device->os_version,
            'app_version' => $device->app_version,
            'is_trusted' => (bool) $device->is_trusted,
            'is_current' => $isCurrent,
            'trusted_at' => $device->trusted_at?->toIso8601String(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'last_login_at' => $device->last_login_at?->toIso8601String(),
            'last_login_ip' => $device->last_login_ip,
        ];
    }
}
