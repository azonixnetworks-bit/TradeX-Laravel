<?php

namespace App\Services\Profile;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountSettingsApiService
{
    public function __construct(
        private readonly ProfilePresenter $profilePresenter,
        private readonly TotpService $totpService,
        private readonly AccessTokenService $accessTokens
    ) {}

    public function overview(User $user, Request $request): array
    {
        $user->loadMissing('profile');

        return [
            'profile' => $this->profilePresenter->present($user),
            'account_security' => $this->security($user, $request),
            'existing_profile_actions' => [
                'language_preferences' => 'PATCH /api/v1/profile/preferences',
                'financial_profile' => 'PATCH /api/v1/profile/financial',
                'whatsapp_change_request' => 'POST /api/v1/profile/whatsapp/change/request',
                'whatsapp_change_confirm' => 'POST /api/v1/profile/whatsapp/change/confirm',
                'telegram_change_request' => 'POST /api/v1/profile/telegram/change/request',
                'telegram_change_confirm' => 'POST /api/v1/profile/telegram/change/confirm',
            ],
        ];
    }

    public function security(User $user, Request $request): array
    {
        $currentDevice = $this->currentDevice($user, $request);
        $activeDevices = $user->devices()->active()->get();
        $mobileLimit = max(
            0,
            (int) config(
                'tradex_mobile_security.customer_devices.mobile_active_limit',
                2
            )
        );
        $desktopLimit = max(
            0,
            (int) config(
                'tradex_mobile_security.customer_devices.desktop_active_limit',
                1
            )
        );

        return [
            'verification' => [
                'email_verified' => $user->email_verified_at !== null,
                'whatsapp_verified' => $user->whatsapp_verified_at !== null,
                'totp_confirmed' => $user->hasConfirmedTotp(),
            ],
            'sensitive_change_policy' => [
                'financial_profile_requires_totp' => true,
                'whatsapp_change_requires_totp_and_email_otp' => true,
                'telegram_change_requires_totp_and_email_otp' => true,
                'remote_device_revoke_requires_totp' => true,
            ],
            'device_policy' => [
                'maximum_active_mobile_devices' => $mobileLimit,
                'maximum_active_desktop_devices' => $desktopLimit,
                'active_mobile_devices' => $activeDevices
                    ->where('device_slot', UserDevice::SLOT_MOBILE)
                    ->count(),
                'active_desktop_devices' => $activeDevices
                    ->where('device_slot', UserDevice::SLOT_DESKTOP)
                    ->count(),
            ],
            'current_device' => $this->deviceSummary($currentDevice, $currentDevice),
            'current_session' => [
                'device_bound' => true,
                'trusted_device_required' => true,
            ],
        ];
    }

    public function devices(User $user, Request $request): array
    {
        $currentDevice = $this->currentDevice($user, $request);
        $mobileLimit = max(
            0,
            (int) config(
                'tradex_mobile_security.customer_devices.mobile_active_limit',
                2
            )
        );
        $desktopLimit = max(
            0,
            (int) config(
                'tradex_mobile_security.customer_devices.desktop_active_limit',
                1
            )
        );

        $devices = $user->devices()
            ->orderByRaw(
                'CASE WHEN id = ? THEN 0 ELSE 1 END',
                [$currentDevice->getKey()]
            )
            ->orderByDesc('is_active')
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get();

        return [
            'items' => $devices
                ->map(fn (UserDevice $device): array => $this->deviceSummary($device, $currentDevice))
                ->values()
                ->all(),
            'policy' => [
                'maximum_active_mobile_devices' => $mobileLimit,
                'maximum_active_desktop_devices' => $desktopLimit,
            ],
            'current_device_revoke_endpoint' => 'POST /api/v1/auth/device/revoke',
        ];
    }

    public function revokeRemoteDevice(
        User $user,
        string $deviceUuid,
        string $totpCode,
        Request $request
    ): array {
        $currentDevice = $this->currentDevice($user, $request);

        $target = $user->devices()
            ->where('uuid', $deviceUuid)
            ->first();

        if (! $target instanceof UserDevice) {
            throw new AuthApiException(404, 'DEVICE_NOT_AVAILABLE', 'This device is not available.');
        }

        if ((int) $target->getKey() === (int) $currentDevice->getKey()) {
            throw new AuthApiException(
                409,
                'CURRENT_DEVICE_REVOKE_ROUTE_REQUIRED',
                'Use the current-device revoke endpoint to revoke this device.'
            );
        }

        if ($target->isRevoked()) {
            throw new AuthApiException(409, 'DEVICE_ALREADY_REVOKED', 'This device has already been revoked.');
        }

        $revoked = DB::transaction(function () use ($user, $target, $totpCode, $request): UserDevice {
            $locked = UserDevice::query()
                ->whereKey($target->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof UserDevice) {
                throw new AuthApiException(404, 'DEVICE_NOT_AVAILABLE', 'This device is not available.');
            }

            if ($locked->isRevoked()) {
                throw new AuthApiException(409, 'DEVICE_ALREADY_REVOKED', 'This device has already been revoked.');
            }

            $this->totpService->consume($user, $totpCode);

            $locked->forceFill([
                'is_active' => false,
                'revoked_at' => now(),
                'revoke_reason' => 'USER_REMOTE_REVOKE',
            ])->save();

            $refreshCount = UserRefreshToken::query()
                ->where('user_id', $user->getKey())
                ->where('user_device_id', $locked->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'USER_REMOTE_DEVICE_REVOKE',
                ]);

            $accessCount = $this->accessTokens->revokeDevice($user, $locked);

            AuditLog::record(
                'USER',
                $user->getKey(),
                'PROFILE_REMOTE_DEVICE_REVOKED',
                'PROFILE',
                'USER_DEVICE',
                $locked->getKey(),
                null,
                [
                    'device_uuid' => $locked->uuid,
                    'device_slot' => $locked->device_slot,
                    'platform' => $locked->platform,
                    'refresh_tokens_revoked' => $refreshCount,
                    'access_tokens_revoked' => $accessCount,
                ],
                $request->ip(),
                $request->userAgent()
            );

            return $locked->refresh();
        }, 3);

        return [
            'device' => $this->deviceSummary($revoked, $currentDevice),
            'current_session_preserved' => true,
        ];
    }

    private function currentDevice(User $user, Request $request): UserDevice
    {
        $device = $request->attributes->get('tradex_user_device');

        if (
            ! $device instanceof UserDevice
            || (int) $device->user_id !== (int) $user->getKey()
        ) {
            throw new AuthApiException(
                401,
                'DEVICE_NOT_FOUND',
                'The current TradeX device session is unavailable.'
            );
        }

        return $device;
    }

    private function deviceSummary(UserDevice $device, UserDevice $currentDevice): array
    {
        return [
            'uuid' => $device->uuid,
            'is_current' => (int) $device->getKey() === (int) $currentDevice->getKey(),
            'slot' => $device->device_slot,
            'platform' => $device->platform,
            'device_name' => $device->device_name,
            'device_model' => $device->device_model,
            'os_version' => $device->os_version,
            'app_version' => $device->app_version,
            'trusted' => (bool) $device->is_trusted,
            'active' => ! $device->isRevoked(),
            'registered_at' => $device->registered_at?->toISOString(),
            'trusted_at' => $device->trusted_at?->toISOString(),
            'last_seen_at' => $device->last_seen_at?->toISOString(),
            'last_login_at' => $device->last_login_at?->toISOString(),
            'revoked_at' => $device->revoked_at?->toISOString(),
        ];
    }
}
