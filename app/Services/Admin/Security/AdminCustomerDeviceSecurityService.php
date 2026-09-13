<?php

namespace App\Services\Admin\Security;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use App\Services\Auth\AccessTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminCustomerDeviceSecurityService
{
    public function __construct(
        private readonly AccessTokenService $accessTokens
    ) {
    }

    public function revoke(
        UserDevice $device,
        AdminUser $actor,
        Request $request
    ): bool {
        if (! $device->is_active || $device->revoked_at !== null) {
            return false;
        }

        return DB::transaction(function () use ($device, $actor, $request): bool {
            $user = $device->user()->firstOrFail();

            $device->forceFill([
                'is_active' => false,
                'is_trusted' => false,
                'revoked_at' => now(),
                'revoked_by_admin_id' => $actor->getKey(),
                'revoke_reason' => 'ADMIN_FORCE_REVOKE',
            ])->save();

            $refreshCount = UserRefreshToken::query()
                ->where('user_id', $user->getKey())
                ->where('user_device_id', $device->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'ADMIN_FORCE_REVOKE',
                ]);

            $accessCount = $this->accessTokens->revokeDevice($user, $device);

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $actor->getKey(),
                action: 'CUSTOMER_DEVICE_FORCE_REVOKED',
                module: 'SECURITY',
                targetType: 'USER_DEVICE',
                targetId: $device->getKey(),
                newValues: [
                    'user_id' => $user->getKey(),
                    'user_uuid' => $user->uuid,
                    'device_uuid' => $device->uuid,
                    'platform' => $device->platform,
                    'device_name' => $device->device_name,
                    'refresh_tokens_revoked' => $refreshCount,
                    'access_tokens_revoked' => $accessCount,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return true;
        });
    }
}
