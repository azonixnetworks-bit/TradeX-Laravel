<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Security\UserDeviceSecurityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDeviceSecurityMutationTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | Revoke Single Device
    |--------------------------------------------------------------------------
    */

    public function test_authorized_admin_can_revoke_device_and_all_its_refresh_tokens(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.devices.revoke',
            ]);

        $user =
            $this->createUser();


        $deviceId =
            $this->createDevice(
                user: $user,
                slot: 'MOBILE',
                platform: 'ANDROID',
                deviceName: 'Security Test Phone'
            );


        $activeTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $deviceId,
                expiresAt: now()->addDay()
            );


        $expiredTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $deviceId,
                expiresAt: now()->subHour()
            );


        $reason =
            'Customer reported this trusted device as lost.';


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.devices.revoke',
                        [
                            'user' =>
                                $user,

                            'device' =>
                                $deviceId,
                        ]
                    ),
                    [
                        'reason' =>
                            $reason,
                    ]
                );


        $response->assertRedirect(
            route(
                'admin.users.show',
                $user
            )
        );


        $response->assertSessionHas(
            'success'
        );


        /*
        |--------------------------------------------------------------------------
        | Device Must Be Terminally Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $deviceId,

                'user_id' =>
                    $user->id,

                'is_active' =>
                    0,

                'is_trusted' =>
                    0,

                'revoked_by_admin_id' =>
                    $admin->id,

                'revoke_reason' =>
                    $reason,
            ]
        );


        $device =
            DB::table(
                'user_devices'
            )
                ->where(
                    'id',
                    $deviceId
                )
                ->first();


        $this->assertNotNull(
            $device
        );

        $this->assertNotNull(
            $device->revoked_at
        );


        /*
        |--------------------------------------------------------------------------
        | Active Token Must Be Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertRefreshTokenRevoked(
            $activeTokenId,
            'DEVICE_REVOKED: '
                . $reason
        );


        /*
        |--------------------------------------------------------------------------
        | Expired But Non-Revoked Token Must Also Become Terminally Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertRefreshTokenRevoked(
            $expiredTokenId,
            'DEVICE_REVOKED: '
                . $reason
        );
    }


    public function test_device_user_foreign_key_is_normalized_to_an_integer_for_ownership_guards(): void
    {
        $user =
            $this->createUser();

        $deviceId =
            $this->createDevice(
                user: $user,
                slot: 'MOBILE',
                platform: 'ANDROID',
                deviceName: 'Ownership Type Test Phone'
            );

        $device =
            UserDevice::query()
                ->findOrFail($deviceId);

        /*
        |------------------------------------------------------------------
        | MySQL can hydrate this foreign key as a string. The cast ensures
        | ownership checks cannot reject the real owner because of type only.
        |------------------------------------------------------------------
        */
        $device->setRawAttributes([
            ...$device->getAttributes(),
            'user_id' => (string) $user->id,
        ]);

        $this->assertSame(
            $user->id,
            $device->user_id
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Revoke Releases the Mobile and Desktop Slots
    |--------------------------------------------------------------------------
    |
    | An administrator's revoke action must remove the active device from its
    | slot. The customer may then enrol either the same installation again or a
    | different installation, without carrying the old trust state forward.
    |
    */

    public function test_admin_revocation_releases_mobile_and_desktop_slots_for_same_or_new_installations(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.devices.revoke',
            ]);

        $user =
            $this->createUser();

        $service =
            $this->app->make(
                UserDeviceSecurityService::class
            );

        foreach ([
            'ANDROID' => 'MOBILE',
            'WINDOWS' => 'DESKTOP',
        ] as $platform => $slot) {

            $sameInstallation =
                'tradex-test-installation-'
                . Str::uuid()
                    ->toString();

            $initial =
                $service->registerDevice(
                    user: $user,
                    deviceData: [
                        'platform' => $platform,
                        'installation_id' => $sameInstallation,
                        'device_name' => "Initial {$slot} Device",
                    ]
                );

            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.devices.revoke',
                        [
                            'user' => $user,
                            'device' => $initial,
                        ]
                    ),
                    [
                        'reason' => "Customer requested {$slot} replacement.",
                    ]
                )
                ->assertRedirect(
                    route(
                        'admin.users.show',
                        $user
                    )
                );

            $sameInstallationDevice =
                $service->registerDevice(
                    user: $user,
                    deviceData: [
                        'platform' => $platform,
                        'installation_id' => $sameInstallation,
                        'device_name' => "Re-enrolled {$slot} Device",
                    ]
                );

            $this->assertSame(
                $initial->id,
                $sameInstallationDevice->id
            );

            $this->assertTrue(
                $sameInstallationDevice->is_active
            );

            $this->assertFalse(
                $sameInstallationDevice->is_trusted
            );

            $this->assertNull(
                $sameInstallationDevice->revoked_at
            );

            $service->revokeDevice(
                device: $sameInstallationDevice,
                reason: "Customer requested a new {$slot} device.",
                admin: $admin
            );

            $replacement =
                $service->registerDevice(
                    user: $user,
                    deviceData: [
                        'platform' => $platform,
                        'installation_id' =>
                            'tradex-replacement-installation-'
                            . Str::uuid()
                                ->toString(),
                        'device_name' => "Replacement {$slot} Device",
                    ]
                );

            $this->assertNotSame(
                $initial->id,
                $replacement->id
            );

            $this->assertSame(
                $slot,
                $replacement->device_slot
            );

            $this->assertSame(
                1,
                UserDevice::query()
                    ->where('user_id', $user->id)
                    ->where('device_slot', $slot)
                    ->active()
                    ->count()
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Reset All Device Slots
    |--------------------------------------------------------------------------
    */

    public function test_authorized_admin_can_reset_mobile_and_desktop_device_slots(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.devices.reset',
            ]);

        $user =
            $this->createUser();


        $mobileDeviceId =
            $this->createDevice(
                user: $user,
                slot: 'MOBILE',
                platform: 'ANDROID',
                deviceName: 'Primary Mobile'
            );


        $desktopDeviceId =
            $this->createDevice(
                user: $user,
                slot: 'DESKTOP',
                platform: 'WINDOWS',
                deviceName: 'Primary Desktop'
            );


        $mobileTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $mobileDeviceId,
                expiresAt: now()->addDay()
            );


        $desktopTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $desktopDeviceId,
                expiresAt: now()->addDay()
            );


        $reason =
            'Administrative trusted-device reset requested by customer.';


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->post(
                    route(
                        'admin.users.devices.reset',
                        $user
                    ),
                    [
                        'reason' =>
                            $reason,
                    ]
                );


        $response->assertRedirect(
            route(
                'admin.users.show',
                $user
            )
        );


        $response->assertSessionHas(
            'success',
            '2 active device(s) revoked and device slots reset successfully.'
        );


        /*
        |--------------------------------------------------------------------------
        | Mobile Device Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertDeviceRevokedByAdmin(
            deviceId:
                $mobileDeviceId,

            adminId:
                $admin->id,

            reason:
                $reason
        );


        /*
        |--------------------------------------------------------------------------
        | Desktop Device Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertDeviceRevokedByAdmin(
            deviceId:
                $desktopDeviceId,

            adminId:
                $admin->id,

            reason:
                $reason
        );


        /*
        |--------------------------------------------------------------------------
        | Device Tokens Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertRefreshTokenRevoked(
            $mobileTokenId,
            'DEVICE_REVOKED: '
                . $reason
        );


        $this->assertRefreshTokenRevoked(
            $desktopTokenId,
            'DEVICE_REVOKED: '
                . $reason
        );


        /*
        |--------------------------------------------------------------------------
        | 1 Mobile + 1 Desktop Slots Must Now Be Free
        |--------------------------------------------------------------------------
        */

        $activeMobileCount =
            DB::table(
                'user_devices'
            )
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'device_slot',
                    'MOBILE'
                )
                ->where(
                    'is_active',
                    1
                )
                ->whereNull(
                    'revoked_at'
                )
                ->count();


        $activeDesktopCount =
            DB::table(
                'user_devices'
            )
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'device_slot',
                    'DESKTOP'
                )
                ->where(
                    'is_active',
                    1
                )
                ->whereNull(
                    'revoked_at'
                )
                ->count();


        $this->assertSame(
            0,
            $activeMobileCount
        );


        $this->assertSame(
            0,
            $activeDesktopCount
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Sign Out All Sessions
    |--------------------------------------------------------------------------
    */

    public function test_sign_out_all_sessions_revokes_tokens_but_preserves_trusted_devices(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.sessions.revoke',
            ]);

        $user =
            $this->createUser();


        $mobileDeviceId =
            $this->createDevice(
                user: $user,
                slot: 'MOBILE',
                platform: 'ANDROID',
                deviceName: 'Trusted Mobile'
            );


        $desktopDeviceId =
            $this->createDevice(
                user: $user,
                slot: 'DESKTOP',
                platform: 'WINDOWS',
                deviceName: 'Trusted Desktop'
            );


        $mobileTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $mobileDeviceId,
                expiresAt: now()->addDay()
            );


        $desktopTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $desktopDeviceId,
                expiresAt: now()->addDays(2)
            );


        $reason =
            'Administrator terminated all active login sessions.';


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->post(
                    route(
                        'admin.users.sessions.revoke',
                        $user
                    ),
                    [
                        'reason' =>
                            $reason,
                    ]
                );


        $response->assertRedirect(
            route(
                'admin.users.show',
                $user
            )
        );


        $response->assertSessionHas(
            'success',
            '2 active refresh session(s) revoked successfully.'
        );


        /*
        |--------------------------------------------------------------------------
        | Refresh Tokens Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertRefreshTokenRevoked(
            $mobileTokenId,
            $reason
        );


        $this->assertRefreshTokenRevoked(
            $desktopTokenId,
            $reason
        );


        /*
        |--------------------------------------------------------------------------
        | Mobile Device Must Remain Trusted + Active
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $mobileDeviceId,

                'user_id' =>
                    $user->id,

                'is_active' =>
                    1,

                'is_trusted' =>
                    1,

                'revoked_at' =>
                    null,

                'revoke_reason' =>
                    null,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Desktop Device Must Remain Trusted + Active
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $desktopDeviceId,

                'user_id' =>
                    $user->id,

                'is_active' =>
                    1,

                'is_trusted' =>
                    1,

                'revoked_at' =>
                    null,

                'revoke_reason' =>
                    null,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Active Session Count Vs Expired Token
    |--------------------------------------------------------------------------
    */

    public function test_expired_token_is_revoked_but_not_counted_as_active_session(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.sessions.revoke',
            ]);

        $user =
            $this->createUser();


        $deviceId =
            $this->createDevice(
                user: $user,
                slot: 'MOBILE',
                platform: 'ANDROID',
                deviceName: 'Session Count Test Device'
            );


        $activeTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $deviceId,
                expiresAt: now()->addHour()
            );


        $expiredTokenId =
            $this->createRefreshToken(
                user: $user,
                deviceId: $deviceId,
                expiresAt: now()->subHour()
            );


        $reason =
            'Session count hardening regression test.';


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->post(
                    route(
                        'admin.users.sessions.revoke',
                        $user
                    ),
                    [
                        'reason' =>
                            $reason,
                    ]
                );


        /*
         * One token was active.
         * One token was already expired.
         *
         * Both must become terminally revoked,
         * but success count must report only one active session.
         */

        $response->assertSessionHas(
            'success',
            '1 active refresh session(s) revoked successfully.'
        );


        $this->assertRefreshTokenRevoked(
            $activeTokenId,
            $reason
        );


        $this->assertRefreshTokenRevoked(
            $expiredTokenId,
            $reason
        );


        /*
         * Trusted device must still exist because this was
         * session revocation, not device revocation.
         */

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $deviceId,

                'is_active' =>
                    1,

                'is_trusted' =>
                    1,

                'revoked_at' =>
                    null,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Already Revoked Device Must Not Be Mutated Again
    |--------------------------------------------------------------------------
    */

    public function test_already_revoked_device_cannot_be_revoked_again(): void
    {
        $admin =
            $this->createAdminWithPermissions([
                'users.devices.revoke',
            ]);

        $user =
            $this->createUser();


        $originalReason =
            'Previously revoked test device.';


        $deviceId =
            $this->createRevokedDevice(
                user:
                    $user,

                admin:
                    $admin,

                reason:
                    $originalReason
            );


        $originalRevokedAt =
            DB::table(
                'user_devices'
            )
                ->where(
                    'id',
                    $deviceId
                )
                ->value(
                    'revoked_at'
                );


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.devices.revoke',
                        [
                            'user' =>
                                $user,

                            'device' =>
                                $deviceId,
                        ]
                    ),
                    [
                        'reason' =>
                            'Second revocation attempt should not replace state.',
                    ]
                );


        $response->assertRedirect(
            route(
                'admin.users.show',
                $user
            )
        );


        $response->assertSessionHas(
            'error',
            'This device is already revoked.'
        );


        $device =
            DB::table(
                'user_devices'
            )
                ->where(
                    'id',
                    $deviceId
                )
                ->first();


        $this->assertNotNull(
            $device
        );


        $this->assertSame(
            $originalReason,
            $device->revoke_reason
        );


        $this->assertSame(
            $originalRevokedAt,
            $device->revoked_at
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Fixture
    |--------------------------------------------------------------------------
    */

    private function createAdminWithPermissions(
        array $permissionCodes
    ): AdminUser {

        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'Device Security Test Admin',

                    'email' =>
                        'device-security-'
                        . Str::lower(
                            Str::random(12)
                        )
                        . '@tradex.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'TestingPassword123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,

                    'locked_until' =>
                        null,
                ]);


        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'DEVICE_SECURITY_'
                        . strtoupper(
                            Str::random(10)
                        ),

                    'name' =>
                        'Device Security Test Role',

                    'description' =>
                        'Automated ADMIN-02E device security test role.',

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);


        $admin
            ->roles()
            ->attach(
                $role->id
            );


        foreach (
            $permissionCodes
            as $permissionCode
        ) {

            $permission =
                AdminPermission::query()
                    ->create([
                        'code' =>
                            $permissionCode,

                        'name' =>
                            $permissionCode,

                        'module' =>
                            'USERS',

                        'description' =>
                            'ADMIN-02E automated test permission.',
                    ]);


            $role
                ->permissions()
                ->attach(
                    $permission->id
                );
        }


        return $admin->fresh();
    }


    /*
    |--------------------------------------------------------------------------
    | User Fixture
    |--------------------------------------------------------------------------
    */

    private function createUser(): User
    {
        return User::factory()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),

                'whatsapp_number' =>
                    '+9477'
                    . random_int(
                        1000000,
                        9999999
                    ),

                'whatsapp_verified_at' =>
                    now(),

                'referral_code' =>
                    'SEC'
                    . strtoupper(
                        Str::random(8)
                    ),

                'country_code' =>
                    'LK',

                'language_code' =>
                    'EN',

                'account_type' =>
                    'FREE',

                'status' =>
                    'ACTIVE',
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Active Trusted Device Fixture
    |--------------------------------------------------------------------------
    */

    private function createDevice(
        User $user,
        string $slot,
        string $platform,
        string $deviceName
    ): int {

        $now =
            now();


        return DB::table(
            'user_devices'
        )->insertGetId([
            'uuid' =>
                Str::uuid()
                    ->toString(),

            'user_id' =>
                $user->id,

            'device_slot' =>
                $slot,

            'platform' =>
                $platform,

            'device_name' =>
                $deviceName,

            'device_model' =>
                'TradeX Test Device',

            'os_version' =>
                '1.0',

            'app_version' =>
                '1.0',

            'installation_id_hash' =>
                hash(
                    'sha256',
                    Str::uuid()
                        ->toString()
                ),

            'device_public_key' =>
                null,

            'is_trusted' =>
                1,

            'is_active' =>
                1,

            'registered_at' =>
                $now,

            'trusted_at' =>
                $now,

            'last_seen_at' =>
                $now,

            'last_login_at' =>
                $now,

            'last_login_ip' =>
                '127.0.0.1',

            'revoked_at' =>
                null,

            'revoked_by_admin_id' =>
                null,

            'revoke_reason' =>
                null,

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Already Revoked Device Fixture
    |--------------------------------------------------------------------------
    */

    private function createRevokedDevice(
        User $user,
        AdminUser $admin,
        string $reason
    ): int {

        $now =
            now();


        return DB::table(
            'user_devices'
        )->insertGetId([
            'uuid' =>
                Str::uuid()
                    ->toString(),

            'user_id' =>
                $user->id,

            'device_slot' =>
                'MOBILE',

            'platform' =>
                'ANDROID',

            'device_name' =>
                'Historical Revoked Device',

            'device_model' =>
                'TradeX Test Device',

            'os_version' =>
                '1.0',

            'app_version' =>
                '1.0',

            'installation_id_hash' =>
                hash(
                    'sha256',
                    Str::uuid()
                        ->toString()
                ),

            'device_public_key' =>
                null,

            'is_trusted' =>
                0,

            'is_active' =>
                0,

            'registered_at' =>
                $now
                    ->copy()
                    ->subDays(5),

            'trusted_at' =>
                $now
                    ->copy()
                    ->subDays(5),

            'last_seen_at' =>
                $now
                    ->copy()
                    ->subDays(2),

            'last_login_at' =>
                $now
                    ->copy()
                    ->subDays(2),

            'last_login_ip' =>
                '127.0.0.1',

            'revoked_at' =>
                $now
                    ->copy()
                    ->subDay(),

            'revoked_by_admin_id' =>
                $admin->id,

            'revoke_reason' =>
                $reason,

            'created_at' =>
                $now
                    ->copy()
                    ->subDays(5),

            'updated_at' =>
                $now
                    ->copy()
                    ->subDay(),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Refresh Token Fixture
    |--------------------------------------------------------------------------
    */

    private function createRefreshToken(
        User $user,
        int $deviceId,
        $expiresAt
    ): int {

        $now =
            now();


        return DB::table(
            'user_refresh_tokens'
        )->insertGetId([
            'uuid' =>
                Str::uuid()
                    ->toString(),

            'user_id' =>
                $user->id,

            'user_device_id' =>
                $deviceId,

            'token_hash' =>
                hash(
                    'sha256',
                    Str::random(64)
                ),

            'family_uuid' =>
                Str::uuid()
                    ->toString(),

            'expires_at' =>
                $expiresAt,

            'last_used_at' =>
                null,

            'revoked_at' =>
                null,

            'revoke_reason' =>
                null,

            'created_at' =>
                $now,

            'updated_at' =>
                $now,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Assertion — Device Revoked By Admin
    |--------------------------------------------------------------------------
    */

    private function assertDeviceRevokedByAdmin(
        int $deviceId,
        int $adminId,
        string $reason
    ): void {

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $deviceId,

                'is_active' =>
                    0,

                'is_trusted' =>
                    0,

                'revoked_by_admin_id' =>
                    $adminId,

                'revoke_reason' =>
                    $reason,
            ]
        );


        $revokedAt =
            DB::table(
                'user_devices'
            )
                ->where(
                    'id',
                    $deviceId
                )
                ->value(
                    'revoked_at'
                );


        $this->assertNotNull(
            $revokedAt
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Assertion — Refresh Token Revoked
    |--------------------------------------------------------------------------
    */

    private function assertRefreshTokenRevoked(
        int $tokenId,
        string $reason
    ): void {

        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' =>
                    $tokenId,

                'revoke_reason' =>
                    $reason,
            ]
        );


        $revokedAt =
            DB::table(
                'user_refresh_tokens'
            )
                ->where(
                    'id',
                    $tokenId
                )
                ->value(
                    'revoked_at'
                );


        $this->assertNotNull(
            $revokedAt
        );
    }
}
