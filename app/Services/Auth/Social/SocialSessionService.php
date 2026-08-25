<?php

namespace App\Services\Auth\Social;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Auth\AccessTokenService;
use App\Services\Auth\DeviceBindingService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SocialSessionService
{
    public function __construct(
        private readonly DeviceBindingService $devices,
        private readonly AccessTokenService $accessTokens,
        private readonly RefreshTokenService $refreshTokens,
    ) {
    }

    public function issue(User $user, array $payload, Request $request, string $auditEvent, array $context = []): array
    {
        $device = $this->devices->bindAfterStrongLogin($user, $payload, $request);
        $familyUuid = (string) Str::uuid();
        $access = $this->accessTokens->issue($user, $device, $familyUuid);
        $refresh = $this->refreshTokens->issueInitial($user, $device, $familyUuid);

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        AuditLog::record(
            'USER',
            $user->getKey(),
            $auditEvent,
            'AUTH',
            'USER_DEVICE',
            $device->getKey(),
            null,
            array_merge([
                'device_slot' => $device->device_slot,
                'platform' => $device->platform,
                'session_uuid' => $familyUuid,
            ], $context),
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
    }
}
