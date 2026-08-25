<?php

namespace App\Services\Security\QuickUnlock;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserDeviceQuickUnlockSetting;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuickUnlockService
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly QuickUnlockPolicyService $policy
    ) {
    }

    public function show(
        User $user,
        UserDevice $device
    ): array {
        $this->assertMobileDevice(
            $device
        );

        $setting =
            UserDeviceQuickUnlockSetting::query()
                ->where(
                    'user_device_id',
                    $device->getKey()
                )
                ->first();

        $enrolled = $setting !== null
            && (bool) $setting->enabled;

        $trustMatches =
            $enrolled
            && $this->trustEpochMatches(
                $setting,
                $device
            );

        return [
            'device_uuid' =>
                $device->uuid,

            'enabled' =>
                $trustMatches,

            'method' =>
                $trustMatches
                    ? $setting->method
                    : null,

            'enrolled' =>
                $enrolled,

            'requires_reenrollment' =>
                $enrolled
                && ! $trustMatches,

            'policy' =>
                $this->policy->policy(),
        ];
    }

    public function update(
        User $user,
        UserDevice $device,
        bool $enabled,
        ?string $method,
        string $totpCode,
        Request $request
    ): array {
        $this->assertMobileDevice(
            $device
        );

        DB::transaction(
            function () use (
                $user,
                $device,
                $enabled,
                $method,
                $totpCode,
                $request
            ): void {
                $lockedUser = User::query()
                    ->whereKey(
                        $user->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedDevice = UserDevice::query()
                    ->whereKey(
                        $device->getKey()
                    )
                    ->where(
                        'user_id',
                        $lockedUser->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertMobileDevice(
                    $lockedDevice
                );

                if (
                    $lockedDevice->isRevoked()
                    || ! $lockedDevice->is_trusted
                ) {
                    throw new AuthApiException(
                        403,
                        'QUICK_UNLOCK_DEVICE_NOT_TRUSTED',
                        'Quick unlock can only be changed from an active trusted device.'
                    );
                }

                $this->totp->consume(
                    $lockedUser,
                    $totpCode
                );

                $setting =
                    UserDeviceQuickUnlockSetting::query()
                        ->where(
                            'user_device_id',
                            $lockedDevice->getKey()
                        )
                        ->lockForUpdate()
                        ->first();

                if ($setting === null) {
                    $setting =
                        new UserDeviceQuickUnlockSetting([
                            'user_device_id' =>
                                $lockedDevice->getKey(),
                        ]);
                }

                if ($enabled) {
                    if (
                        ! in_array(
                            $method,
                            UserDeviceQuickUnlockSetting::METHODS,
                            true
                        )
                    ) {
                        throw new AuthApiException(
                            422,
                            'QUICK_UNLOCK_METHOD_INVALID',
                            'The quick unlock method is invalid.'
                        );
                    }

                    if (
                        $method
                        === UserDeviceQuickUnlockSetting::METHOD_BIOMETRIC
                        && ! (bool) config(
                            'tradex_mobile_security.quick_unlock.biometric_allowed',
                            true
                        )
                    ) {
                        throw new AuthApiException(
                            409,
                            'QUICK_UNLOCK_BIOMETRIC_DISABLED',
                            'Biometric quick unlock is currently disabled by policy.'
                        );
                    }

                    if (
                        $lockedDevice->trusted_at
                        === null
                    ) {
                        throw new AuthApiException(
                            409,
                            'QUICK_UNLOCK_TRUST_EPOCH_MISSING',
                            'The current device must complete a strong login before quick unlock can be enabled.'
                        );
                    }

                    $setting->forceFill([
                        'enabled' =>
                            true,

                        'method' =>
                            $method,

                        'trusted_at_snapshot' =>
                            $lockedDevice->trusted_at,

                        'enrolled_at' =>
                            now(),

                        'disabled_at' =>
                            null,
                    ])->save();

                    $action =
                        'QUICK_UNLOCK_ENABLED';
                } else {
                    $setting->forceFill([
                        'enabled' =>
                            false,

                        'method' =>
                            null,

                        'trusted_at_snapshot' =>
                            null,

                        'disabled_at' =>
                            now(),
                    ])->save();

                    $action =
                        'QUICK_UNLOCK_DISABLED';
                }

                AuditLog::record(
                    'USER',
                    $lockedUser->getKey(),
                    $action,
                    'SECURITY',
                    'USER_DEVICE',
                    $lockedDevice->getKey(),
                    null,
                    [
                        'enabled' =>
                            $enabled,

                        'method' =>
                            $enabled
                                ? $method
                                : null,

                        'policy_version' =>
                            (string) config(
                                'tradex_mobile_security.quick_unlock.policy_version',
                                'MOBILE-SEC-01-v1'
                            ),
                    ],
                    $request->ip(),
                    $request->userAgent()
                );
            },
            3
        );

        return $this->show(
            $user->fresh(),
            $device->fresh()
        );
    }

    private function assertMobileDevice(
        UserDevice $device
    ): void {
        if (
            ! $device->isMobile()
            || ! in_array(
                $device->platform,
                [
                    UserDevice::PLATFORM_ANDROID,
                    UserDevice::PLATFORM_IOS,
                ],
                true
            )
        ) {
            throw new AuthApiException(
                422,
                'QUICK_UNLOCK_MOBILE_ONLY',
                'Quick unlock is available only on supported mobile devices.'
            );
        }
    }

    private function trustEpochMatches(
        UserDeviceQuickUnlockSetting $setting,
        UserDevice $device
    ): bool {
        if (
            $setting->trusted_at_snapshot
            === null
            || $device->trusted_at
            === null
        ) {
            return false;
        }

        return $setting
            ->trusted_at_snapshot
            ->getTimestamp()
            === $device
                ->trusted_at
                ->getTimestamp();
    }
}
