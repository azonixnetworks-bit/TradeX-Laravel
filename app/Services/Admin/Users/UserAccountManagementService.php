<?php

namespace App\Services\Admin\Users;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserEntitlement;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountManagementService
{
    public function changeAccountType(
        User $user,
        AdminUser $admin,
        string $accountType,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): User {
        return DB::transaction(function () use (
            $user,
            $admin,
            $accountType,
            $reason,
            $ipAddress,
            $userAgent
        ): User {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $entitlement = UserEntitlement::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('product_code', Product::CODE_EDUCATION_PREMIUM)
                ->lockForUpdate()
                ->first();

            $oldAccountType = $lockedUser->account_type;
            $oldEntitlementStatus = $entitlement?->status;

            if ($accountType === 'PREMIUM') {
                if (! $entitlement instanceof UserEntitlement) {
                    $entitlement = new UserEntitlement([
                        'user_id' => $lockedUser->getKey(),
                        'product_code' => Product::CODE_EDUCATION_PREMIUM,
                    ]);
                }

                $entitlement->forceFill([
                    'status' => UserEntitlement::STATUS_ACTIVE,
                    'activated_at' => $entitlement->activated_at ?? now(),
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ])->save();

                $lockedUser->forceFill([
                    'account_type' => 'PREMIUM',
                    'premium_activated_at' => $lockedUser->premium_activated_at ?? now(),
                ])->save();
            } else {
                if ($entitlement instanceof UserEntitlement
                    && $entitlement->status === UserEntitlement::STATUS_ACTIVE) {
                    $entitlement->forceFill([
                        'status' => UserEntitlement::STATUS_REVOKED,
                        'revoked_at' => now(),
                        'revoke_reason' => 'SUPER_ADMIN_ACCOUNT_TYPE_CHANGE: '.$reason,
                    ])->save();
                }

                $lockedUser->forceFill([
                    'account_type' => 'FREE',
                ])->save();
            }

            $newEntitlementStatus = $entitlement?->fresh()?->status;

            if ($oldAccountType === $accountType
                && $oldEntitlementStatus === $newEntitlementStatus) {
                throw ValidationException::withMessages([
                    'account_type' => 'The selected account type is already active.',
                ]);
            }

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'USER_ACCOUNT_TYPE_CHANGED',
                module: 'USERS',
                targetType: 'USER',
                targetId: $lockedUser->getKey(),
                oldValues: [
                    'account_type' => $oldAccountType,
                    'education_premium_entitlement_status' => $oldEntitlementStatus,
                ],
                newValues: [
                    'account_type' => $accountType,
                    'education_premium_entitlement_status' => $newEntitlementStatus,
                    'reason' => $reason,
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );

            return $lockedUser->fresh();
        }, 3);
    }

    public function softDelete(
        User $user,
        AdminUser $admin,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        DB::transaction(function () use (
            $user,
            $admin,
            $reason,
            $ipAddress,
            $userAgent
        ): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $revokedSessions = UserRefreshToken::query()
                ->where('user_id', $lockedUser->getKey())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revoke_reason' => 'USER_ACCOUNT_DELETED: '.$reason,
                    'updated_at' => now(),
                ]);

            $revokedDevices = UserDevice::query()
                ->where('user_id', $lockedUser->getKey())
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'is_trusted' => false,
                    'revoked_at' => now(),
                    'revoke_reason' => 'USER_ACCOUNT_DELETED: '.$reason,
                    'revoked_by_admin_id' => $admin->getKey(),
                    'updated_at' => now(),
                ]);

            $oldValues = [
                'email' => $lockedUser->email,
                'account_type' => $lockedUser->account_type,
                'status' => $lockedUser->status,
            ];

            $lockedUser->forceFill([
                'status' => 'BLOCKED',
                'status_reason' => 'USER_ACCOUNT_DELETED: '.$reason,
                'status_changed_at' => now(),
                'status_changed_by_admin_id' => $admin->getKey(),
                'blocked_at' => now(),
            ])->save();

            $lockedUser->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'USER_ACCOUNT_DELETED',
                module: 'USERS',
                targetType: 'USER',
                targetId: $lockedUser->getKey(),
                oldValues: $oldValues,
                newValues: [
                    'deleted' => true,
                    'reason' => $reason,
                    'revoked_sessions' => $revokedSessions,
                    'revoked_devices' => $revokedDevices,
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );
        }, 3);
    }
}
