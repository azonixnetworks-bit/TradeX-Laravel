<?php

namespace App\Services\Security;

use App\Exceptions\DeviceSecurityException;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserDeviceSecurityService
{
    /*
    |--------------------------------------------------------------------------
    | Device Registration
    |--------------------------------------------------------------------------
    |
    | This method DOES NOT automatically mark a new device as trusted.
    |
    | Authentication flow later:
    |
    | Password
    |   ↓
    | TOTP
    |   ↓
    | registerDevice()
    |   ↓
    | Additional device verification if required
    |   ↓
    | trustDevice()
    |
    */

    public function registerDevice(
        User $user,
        array $deviceData,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UserDevice {

        $platform = $this->normalizePlatform(
            $deviceData['platform'] ?? null
        );

        $slot = $this->resolveSlot(
            $platform
        );

        $installationId =
            $this->validateInstallationId(
                $deviceData['installation_id']
                ?? null
            );

        $installationHash =
            $this->hashInstallationId(
                $installationId
            );

        return DB::transaction(
            function () use (
                $user,
                $deviceData,
                $platform,
                $slot,
                $installationHash,
                $ipAddress,
                $userAgent
            ) {

                /*
                |--------------------------------------------------------------------------
                | Lock User
                |--------------------------------------------------------------------------
                |
                | All slot registration operations must lock
                | the user row first.
                |
                */

                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | Existing Same Installation
                |--------------------------------------------------------------------------
                */

                $existingDevice = UserDevice::query()
                    ->where(
                        'user_id',
                        $lockedUser->id
                    )
                    ->where(
                        'installation_id_hash',
                        $installationHash
                    )
                    ->lockForUpdate()
                    ->first();


                if (
                    $existingDevice
                    &&
                    $existingDevice->is_active
                    &&
                    $existingDevice->revoked_at === null
                ) {

                    if (
                        $existingDevice->device_slot
                        !== $slot
                    ) {

                        throw new DeviceSecurityException(
                            errorCode:
                                'DEVICE_SLOT_MISMATCH',

                            message:
                                'This installation is registered under a different device slot.',

                            context: [
                                'expected_slot' =>
                                    $existingDevice
                                        ->device_slot,

                                'requested_slot' =>
                                    $slot,
                            ]
                        );
                    }


                    /*
                     * Do not replace the public key of an
                     * already trusted device automatically.
                     */

                    $updates = [
                        'device_name' =>
                            $deviceData['device_name']
                            ?? $existingDevice
                                ->device_name,

                        'device_model' =>
                            $deviceData['device_model']
                            ?? $existingDevice
                                ->device_model,

                        'os_version' =>
                            $deviceData['os_version']
                            ?? $existingDevice
                                ->os_version,

                        'app_version' =>
                            $deviceData['app_version']
                            ?? $existingDevice
                                ->app_version,

                        'last_seen_at' => now(),

                        'last_login_ip' =>
                            $ipAddress,
                    ];


                    if (
                        ! $existingDevice->is_trusted
                        &&
                        ! empty(
                            $deviceData[
                                'device_public_key'
                            ] ?? null
                        )
                    ) {

                        $updates['device_public_key'] =
                            $deviceData[
                                'device_public_key'
                            ];
                    }


                    $existingDevice->update(
                        $updates
                    );


                    return $existingDevice
                        ->fresh();
                }


                /*
                |--------------------------------------------------------------------------
                | Check Slot Occupancy
                |--------------------------------------------------------------------------
                */

                $occupiedDevice =
                    UserDevice::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
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
                        ->lockForUpdate()
                        ->first();


                if ($occupiedDevice) {

                    AuditLog::record(
                        actorType: 'USER',
                        actorId: $lockedUser->id,
                        action:
                            'DEVICE_LIMIT_BLOCKED',
                        module:
                            'DEVICE_SECURITY',
                        targetType:
                            'USER_DEVICE',
                        targetId:
                            $occupiedDevice->id,
                        newValues: [
                            'slot' => $slot,
                            'platform' =>
                                $platform,
                        ],
                        ipAddress:
                            $ipAddress,
                        userAgent:
                            $userAgent
                    );


                    throw new DeviceSecurityException(
                        errorCode:
                            'DEVICE_LIMIT_REACHED',

                        message:
                            "The {$slot} device slot is already occupied.",

                        context: [
                            'slot' => $slot,

                            'current_device_id' =>
                                $occupiedDevice->id,

                            'current_device_name' =>
                                $occupiedDevice
                                    ->device_name,

                            'current_platform' =>
                                $occupiedDevice
                                    ->platform,

                            'registered_at' =>
                                optional(
                                    $occupiedDevice
                                        ->registered_at
                                )?->toISOString(),
                        ]
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Re-register Previously Revoked Same Installation
                |--------------------------------------------------------------------------
                */

                if ($existingDevice) {

                    $existingDevice->update([
                        'device_slot' => $slot,

                        'platform' => $platform,

                        'device_name' =>
                            $deviceData[
                                'device_name'
                            ] ?? null,

                        'device_model' =>
                            $deviceData[
                                'device_model'
                            ] ?? null,

                        'os_version' =>
                            $deviceData[
                                'os_version'
                            ] ?? null,

                        'app_version' =>
                            $deviceData[
                                'app_version'
                            ] ?? null,

                        'device_public_key' =>
                            $deviceData[
                                'device_public_key'
                            ] ?? null,

                        'is_trusted' => false,

                        'is_active' => true,

                        'registered_at' => now(),

                        'trusted_at' => null,

                        'last_seen_at' => now(),

                        'last_login_at' => null,

                        'last_login_ip' =>
                            $ipAddress,

                        'revoked_at' => null,

                        'revoked_by_admin_id' =>
                            null,

                        'revoke_reason' => null,
                    ]);


                    $device =
                        $existingDevice->fresh();

                    $auditAction =
                        'DEVICE_REREGISTERED';

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | New Device
                    |--------------------------------------------------------------------------
                    */

                    $device =
                        UserDevice::create([
                            'uuid' =>
                                Str::uuid()
                                    ->toString(),

                            'user_id' =>
                                $lockedUser->id,

                            'device_slot' =>
                                $slot,

                            'platform' =>
                                $platform,

                            'device_name' =>
                                $deviceData[
                                    'device_name'
                                ] ?? null,

                            'device_model' =>
                                $deviceData[
                                    'device_model'
                                ] ?? null,

                            'os_version' =>
                                $deviceData[
                                    'os_version'
                                ] ?? null,

                            'app_version' =>
                                $deviceData[
                                    'app_version'
                                ] ?? null,

                            'installation_id_hash' =>
                                $installationHash,

                            'device_public_key' =>
                                $deviceData[
                                    'device_public_key'
                                ] ?? null,

                            'is_trusted' =>
                                false,

                            'is_active' =>
                                true,

                            'registered_at' =>
                                now(),

                            'last_seen_at' =>
                                now(),

                            'last_login_ip' =>
                                $ipAddress,
                        ]);


                    $auditAction =
                        'DEVICE_REGISTERED';
                }


                AuditLog::record(
                    actorType: 'USER',
                    actorId: $lockedUser->id,
                    action: $auditAction,
                    module: 'DEVICE_SECURITY',
                    targetType: 'USER_DEVICE',
                    targetId: $device->id,
                    newValues: [
                        'slot' =>
                            $device->device_slot,

                        'platform' =>
                            $device->platform,

                        'device_name' =>
                            $device->device_name,
                    ],
                    ipAddress:
                        $ipAddress,
                    userAgent:
                        $userAgent
                );


                return $device;
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Trust Device
    |--------------------------------------------------------------------------
    */

    public function trustDevice(
        UserDevice $device,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UserDevice {

        return DB::transaction(
            function () use (
                $device,
                $ipAddress,
                $userAgent
            ) {

                $lockedDevice =
                    UserDevice::query()
                        ->whereKey(
                            $device->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    ! $lockedDevice->is_active
                    ||
                    $lockedDevice->revoked_at !== null
                ) {

                    throw new DeviceSecurityException(
                        errorCode:
                            'DEVICE_REVOKED',

                        message:
                            'A revoked device cannot be trusted.'
                    );
                }


                if ($lockedDevice->is_trusted) {
                    return $lockedDevice;
                }


                $lockedDevice->update([
                    'is_trusted' => true,

                    'trusted_at' => now(),

                    'last_seen_at' => now(),
                ]);


                AuditLog::record(
                    actorType: 'USER',

                    actorId:
                        $lockedDevice->user_id,

                    action:
                        'DEVICE_TRUSTED',

                    module:
                        'DEVICE_SECURITY',

                    targetType:
                        'USER_DEVICE',

                    targetId:
                        $lockedDevice->id,

                    newValues: [
                        'slot' =>
                            $lockedDevice
                                ->device_slot,

                        'platform' =>
                            $lockedDevice
                                ->platform,
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );


                return $lockedDevice
                    ->fresh();
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Replace Device
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | Password + TOTP + Email OTP authorization must happen
    | BEFORE calling this method.
    |
    */

    public function replaceDevice(
        User $user,
        array $newDeviceData,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): UserDevice {

        $platform = $this->normalizePlatform(
            $newDeviceData['platform']
            ?? null
        );

        $slot = $this->resolveSlot(
            $platform
        );

        $installationId =
            $this->validateInstallationId(
                $newDeviceData[
                    'installation_id'
                ] ?? null
            );

        $installationHash =
            $this->hashInstallationId(
                $installationId
            );


        return DB::transaction(
            function () use (
                $user,
                $newDeviceData,
                $platform,
                $slot,
                $installationHash,
                $reason,
                $ipAddress,
                $userAgent
            ) {

                $lockedUser =
                    User::query()
                        ->whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();


                $currentDevice =
                    UserDevice::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
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
                        ->lockForUpdate()
                        ->first();


                /*
                 * If exact same installation is already
                 * active, replacement is unnecessary.
                 */

                if (
                    $currentDevice
                    &&
                    hash_equals(
                        (string)
                            $currentDevice
                                ->installation_id_hash,

                        $installationHash
                    )
                ) {

                    return $currentDevice;
                }


                $oldDeviceId =
                    $currentDevice?->id;


                /*
                |--------------------------------------------------------------------------
                | Revoke Old Device
                |--------------------------------------------------------------------------
                */

                if ($currentDevice) {

                    $this->revokeLockedDevice(
                        device:
                            $currentDevice,

                        reason:
                            $reason,

                        adminId:
                            null
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Find Previously Known New Installation
                |--------------------------------------------------------------------------
                */

                $newDevice =
                    UserDevice::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->where(
                            'installation_id_hash',
                            $installationHash
                        )
                        ->lockForUpdate()
                        ->first();


                if ($newDevice) {

                    $newDevice->update([
                        'device_slot' =>
                            $slot,

                        'platform' =>
                            $platform,

                        'device_name' =>
                            $newDeviceData[
                                'device_name'
                            ] ?? null,

                        'device_model' =>
                            $newDeviceData[
                                'device_model'
                            ] ?? null,

                        'os_version' =>
                            $newDeviceData[
                                'os_version'
                            ] ?? null,

                        'app_version' =>
                            $newDeviceData[
                                'app_version'
                            ] ?? null,

                        'device_public_key' =>
                            $newDeviceData[
                                'device_public_key'
                            ] ?? null,

                        /*
                         * Replacement does not automatically
                         * complete trust verification.
                         */

                        'is_trusted' =>
                            false,

                        'is_active' =>
                            true,

                        'registered_at' =>
                            now(),

                        'trusted_at' =>
                            null,

                        'last_seen_at' =>
                            now(),

                        'last_login_at' =>
                            null,

                        'last_login_ip' =>
                            $ipAddress,

                        'revoked_at' =>
                            null,

                        'revoked_by_admin_id' =>
                            null,

                        'revoke_reason' =>
                            null,
                    ]);

                } else {

                    $newDevice =
                        UserDevice::create([
                            'uuid' =>
                                Str::uuid()
                                    ->toString(),

                            'user_id' =>
                                $lockedUser->id,

                            'device_slot' =>
                                $slot,

                            'platform' =>
                                $platform,

                            'device_name' =>
                                $newDeviceData[
                                    'device_name'
                                ] ?? null,

                            'device_model' =>
                                $newDeviceData[
                                    'device_model'
                                ] ?? null,

                            'os_version' =>
                                $newDeviceData[
                                    'os_version'
                                ] ?? null,

                            'app_version' =>
                                $newDeviceData[
                                    'app_version'
                                ] ?? null,

                            'installation_id_hash' =>
                                $installationHash,

                            'device_public_key' =>
                                $newDeviceData[
                                    'device_public_key'
                                ] ?? null,

                            'is_trusted' =>
                                false,

                            'is_active' =>
                                true,

                            'registered_at' =>
                                now(),

                            'last_seen_at' =>
                                now(),

                            'last_login_ip' =>
                                $ipAddress,
                        ]);
                }


                AuditLog::record(
                    actorType: 'USER',

                    actorId:
                        $lockedUser->id,

                    action:
                        'DEVICE_REPLACED',

                    module:
                        'DEVICE_SECURITY',

                    targetType:
                        'USER_DEVICE',

                    targetId:
                        $newDevice->id,

                    oldValues: [
                        'old_device_id' =>
                            $oldDeviceId,

                        'slot' =>
                            $slot,
                    ],

                    newValues: [
                        'new_device_id' =>
                            $newDevice->id,

                        'platform' =>
                            $platform,

                        'reason' =>
                            $reason,
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );


                return $newDevice
                    ->fresh();
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Admin / User Device Revocation
    |--------------------------------------------------------------------------
    */

    public function revokeDevice(
        UserDevice $device,
        string $reason,
        ?AdminUser $admin = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {

        DB::transaction(
            function () use (
                $device,
                $reason,
                $admin,
                $ipAddress,
                $userAgent
            ) {

                /*
                 * Lock user first so every slot-changing
                 * operation uses the same lock order.
                 */

                $lockedUser =
                    User::query()
                        ->whereKey(
                            $device->user_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                $lockedDevice =
                    UserDevice::query()
                        ->whereKey(
                            $device->id
                        )
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if ($lockedDevice->isRevoked()) {
                    return;
                }


                $this->revokeLockedDevice(
                    device:
                        $lockedDevice,

                    reason:
                        $reason,

                    adminId:
                        $admin?->id
                );


                AuditLog::record(
                    actorType:
                        $admin
                            ? 'ADMIN'
                            : 'USER',

                    actorId:
                        $admin?->id
                        ?? $lockedUser->id,

                    action:
                        'DEVICE_REVOKED',

                    module:
                        'DEVICE_SECURITY',

                    targetType:
                        'USER_DEVICE',

                    targetId:
                        $lockedDevice->id,

                    newValues: [
                        'slot' =>
                            $lockedDevice
                                ->device_slot,

                        'platform' =>
                            $lockedDevice
                                ->platform,

                        'reason' =>
                            $reason,
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Reset All Device Slots
    |--------------------------------------------------------------------------
    */

    public function resetUserDevices(
        User $user,
        AdminUser $admin,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {

        return DB::transaction(
            function () use (
                $user,
                $admin,
                $reason,
                $ipAddress,
                $userAgent
            ) {

                $lockedUser =
                    User::query()
                        ->whereKey($user->id)
                        ->lockForUpdate()
                        ->firstOrFail();


                $devices =
                    UserDevice::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->where(
                            'is_active',
                            true
                        )
                        ->whereNull(
                            'revoked_at'
                        )
                        ->lockForUpdate()
                        ->get();


                foreach ($devices as $device) {

                    $this->revokeLockedDevice(
                        device: $device,

                        reason: $reason,

                        adminId: $admin->id
                    );
                }


                AuditLog::record(
                    actorType: 'ADMIN',

                    actorId: $admin->id,

                    action:
                        'DEVICE_SLOTS_RESET',

                    module:
                        'DEVICE_SECURITY',

                    targetType:
                        'USER',

                    targetId:
                        $lockedUser->id,

                    newValues: [
                        'revoked_device_count' =>
                            $devices->count(),

                        'reason' =>
                            $reason,
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );


                return $devices->count();
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Revoke All User Refresh Tokens
    |--------------------------------------------------------------------------
    |
    | HARDENING:
    |
    | 1. User row is locked first.
    | 2. Existing non-revoked refresh tokens are locked.
    | 3. All non-revoked tokens are terminally revoked, including tokens
    |    that may already have expired.
    | 4. Only tokens that were active at the transaction snapshot are
    |    reported as active sessions revoked.
    | 5. Audit record is committed in the SAME transaction.
    |
    */

    public function revokeAllUserRefreshTokens(
        User $user,
        string $reason,
        ?AdminUser $admin = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): int {

        return DB::transaction(
            function () use (
                $user,
                $reason,
                $admin,
                $ipAddress,
                $userAgent
            ) {

                /*
                |--------------------------------------------------------------------------
                | Lock User First
                |--------------------------------------------------------------------------
                */

                $lockedUser =
                    User::query()
                        ->whereKey(
                            $user->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                /*
                |--------------------------------------------------------------------------
                | Stable Revocation Timestamp
                |--------------------------------------------------------------------------
                */

                $revokedAt = now();


                /*
                |--------------------------------------------------------------------------
                | Lock Existing Non-Revoked Tokens
                |--------------------------------------------------------------------------
                */

                $tokenIds =
                    UserRefreshToken::query()
                        ->where(
                            'user_id',
                            $lockedUser->id
                        )
                        ->whereNull(
                            'revoked_at'
                        )
                        ->lockForUpdate()
                        ->pluck(
                            'id'
                        );


                /*
                |--------------------------------------------------------------------------
                | Count Currently Active Sessions
                |--------------------------------------------------------------------------
                |
                | Expired but previously non-revoked token rows are still
                | terminally marked revoked below, but they are not counted
                | as active sessions.
                |
                */

                $activeSessionCount = 0;


                if ($tokenIds->isNotEmpty()) {

                    $activeSessionCount =
                        UserRefreshToken::query()
                            ->whereIn(
                                'id',
                                $tokenIds
                            )
                            ->where(
                                'expires_at',
                                '>',
                                $revokedAt
                            )
                            ->count();


                    /*
                    |--------------------------------------------------------------------------
                    | Revoke Snapshot
                    |--------------------------------------------------------------------------
                    */

                    UserRefreshToken::query()
                        ->whereIn(
                            'id',
                            $tokenIds
                        )
                        ->whereNull(
                            'revoked_at'
                        )
                        ->update([
                            'revoked_at' =>
                                $revokedAt,

                            'revoke_reason' =>
                                $reason,

                            'updated_at' =>
                                $revokedAt,
                        ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Transactional Audit
                |--------------------------------------------------------------------------
                */

                AuditLog::record(
                    actorType:
                        $admin
                            ? 'ADMIN'
                            : 'USER',

                    actorId:
                        $admin?->id
                        ?? $lockedUser->id,

                    action:
                        'USER_SESSIONS_REVOKED',

                    module:
                        'DEVICE_SECURITY',

                    targetType:
                        'USER',

                    targetId:
                        $lockedUser->id,

                    newValues: [
                        'revoked_sessions' =>
                            $activeSessionCount,

                        'revoked_refresh_tokens_total' =>
                            $tokenIds->count(),

                        'reason' =>
                            $reason,
                    ],

                    ipAddress:
                        $ipAddress,

                    userAgent:
                        $userAgent
                );


                return $activeSessionCount;
            },
            3
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Trusted Device Lookup
    |--------------------------------------------------------------------------
    */

    public function findTrustedDevice(
        User $user,
        string $installationId
    ): ?UserDevice {

        $installationHash =
            $this->hashInstallationId(
                $this->validateInstallationId(
                    $installationId
                )
            );


        return UserDevice::query()
            ->where(
                'user_id',
                $user->id
            )
            ->where(
                'installation_id_hash',
                $installationHash
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_trusted',
                true
            )
            ->whereNull(
                'revoked_at'
            )
            ->first();
    }


    /*
    |--------------------------------------------------------------------------
    | Internal Device Revoke
    |--------------------------------------------------------------------------
    */

    private function revokeLockedDevice(
        UserDevice $device,
        string $reason,
        ?int $adminId
    ): void {

        /*
        |--------------------------------------------------------------------------
        | One Consistent Revocation Timestamp
        |--------------------------------------------------------------------------
        */

        $revokedAt = now();


        $device->update([
            'is_active' =>
                false,

            'is_trusted' =>
                false,

            'revoked_at' =>
                $revokedAt,

            'revoked_by_admin_id' =>
                $adminId,

            'revoke_reason' =>
                $reason,
        ]);


        /*
        |--------------------------------------------------------------------------
        | Revoke Every Non-Revoked Refresh Token for Device
        |--------------------------------------------------------------------------
        |
        | This deliberately also marks already-expired but non-revoked token
        | rows as revoked so that a revoked device has a terminal security
        | state across all of its refresh-token records.
        |
        */

        UserRefreshToken::query()
            ->where(
                'user_device_id',
                $device->id
            )
            ->whereNull(
                'revoked_at'
            )
            ->update([
                'revoked_at' =>
                    $revokedAt,

                'revoke_reason' =>
                    'DEVICE_REVOKED: '
                    . $reason,

                'updated_at' =>
                    $revokedAt,
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Platform → Slot
    |--------------------------------------------------------------------------
    |
    | Never trust client-provided "device_slot".
    | Server derives it from the platform.
    |
    */

    private function resolveSlot(
        string $platform
    ): string {

        return match ($platform) {

            UserDevice::PLATFORM_ANDROID,
            UserDevice::PLATFORM_IOS
                =>
                UserDevice::SLOT_MOBILE,


            UserDevice::PLATFORM_WINDOWS,
            UserDevice::PLATFORM_MACOS,
            UserDevice::PLATFORM_WEB
                =>
                UserDevice::SLOT_DESKTOP,


            default =>
                throw new DeviceSecurityException(
                    errorCode:
                        'INVALID_DEVICE_PLATFORM',

                    message:
                        'Unsupported device platform.',

                    context: [
                        'platform' =>
                            $platform,
                    ],

                    httpStatus:
                        422
                ),
        };
    }


    /*
    |--------------------------------------------------------------------------
    | Normalize Platform
    |--------------------------------------------------------------------------
    */

    private function normalizePlatform(
        mixed $platform
    ): string {

        if (! is_string($platform)) {

            throw new DeviceSecurityException(
                errorCode:
                    'INVALID_DEVICE_PLATFORM',

                message:
                    'Device platform is required.',

                httpStatus:
                    422
            );
        }


        $platform = strtoupper(
            trim($platform)
        );


        $allowed = [
            UserDevice::PLATFORM_ANDROID,
            UserDevice::PLATFORM_IOS,
            UserDevice::PLATFORM_WINDOWS,
            UserDevice::PLATFORM_MACOS,
            UserDevice::PLATFORM_WEB,
        ];


        if (
            ! in_array(
                $platform,
                $allowed,
                true
            )
        ) {

            throw new DeviceSecurityException(
                errorCode:
                    'INVALID_DEVICE_PLATFORM',

                message:
                    'Unsupported device platform.',

                context: [
                    'platform' =>
                        $platform,
                ],

                httpStatus:
                    422
            );
        }


        return $platform;
    }


    /*
    |--------------------------------------------------------------------------
    | Validate Installation ID
    |--------------------------------------------------------------------------
    */

    private function validateInstallationId(
        mixed $installationId
    ): string {

        if (! is_string($installationId)) {

            throw new DeviceSecurityException(
                errorCode:
                    'INVALID_INSTALLATION_ID',

                message:
                    'Device installation ID is required.',

                httpStatus:
                    422
            );
        }


        $installationId =
            trim($installationId);


        $length =
            strlen(
                $installationId
            );


        if (
            $length < 16
            ||
            $length > 255
        ) {

            throw new DeviceSecurityException(
                errorCode:
                    'INVALID_INSTALLATION_ID',

                message:
                    'The device installation ID is invalid.',

                httpStatus:
                    422
            );
        }


        return $installationId;
    }


    /*
    |--------------------------------------------------------------------------
    | Installation ID Hash
    |--------------------------------------------------------------------------
    */

    private function hashInstallationId(
        string $installationId
    ): string {

        return hash(
            'sha256',
            $installationId
        );
    }
}