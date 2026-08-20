<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDeviceIdorProtectionTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | Cross-User Device IDOR Protection
    |--------------------------------------------------------------------------
    |
    | Attack:
    |
    | User A:
    | /admin/users/{userA}/devices/{userBDevice}/revoke
    |
    | Expected:
    |
    | 404
    |
    | Device B must remain untouched.
    | Refresh token B must remain untouched.
    |
    */

    public function test_admin_cannot_revoke_another_users_device_through_nested_route(): void
    {
        $admin =
            $this->createAuthorizedAdmin();


        /*
        |--------------------------------------------------------------------------
        | User A
        |--------------------------------------------------------------------------
        */

        $userA =
            $this->createUser(
                'A'
            );


        /*
        |--------------------------------------------------------------------------
        | User B
        |--------------------------------------------------------------------------
        */

        $userB =
            $this->createUser(
                'B'
            );


        /*
        |--------------------------------------------------------------------------
        | Device Owned By User B
        |--------------------------------------------------------------------------
        */

        $userBDeviceId =
            $this->createDevice(
                user:
                    $userB,

                deviceName:
                    'User B Secure Phone'
            );


        /*
        |--------------------------------------------------------------------------
        | Active Refresh Token Owned By User B
        |--------------------------------------------------------------------------
        */

        $userBRefreshTokenId =
            $this->createRefreshToken(
                user:
                    $userB,

                deviceId:
                    $userBDeviceId
            );


        /*
        |--------------------------------------------------------------------------
        | Attempt IDOR Attack
        |--------------------------------------------------------------------------
        |
        | Parent route user = User A
        | Nested device      = User B device
        |
        */

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
                                $userA,

                            'device' =>
                                $userBDeviceId,
                        ]
                    ),
                    [
                        'reason' =>
                            'Attempted cross-user device revocation test.',
                    ]
                );


        /*
        |--------------------------------------------------------------------------
        | Must Fail As Not Found
        |--------------------------------------------------------------------------
        */

        $response
            ->assertNotFound();


        /*
        |--------------------------------------------------------------------------
        | User B Device Must Remain Active
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $userBDeviceId,

                'user_id' =>
                    $userB->id,

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
        | Refresh Token Must Remain Active / Non-Revoked
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' =>
                    $userBRefreshTokenId,

                'user_id' =>
                    $userB->id,

                'user_device_id' =>
                    $userBDeviceId,

                'revoked_at' =>
                    null,

                'revoke_reason' =>
                    null,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Ownership Must Remain Unchanged
        |--------------------------------------------------------------------------
        */

        $this->assertDatabaseMissing(
            'user_devices',
            [
                'id' =>
                    $userBDeviceId,

                'user_id' =>
                    $userA->id,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Reverse Cross-User Attack
    |--------------------------------------------------------------------------
    |
    | Also verify the opposite direction so the protection is not dependent
    | on record ordering or IDs.
    |
    */

    public function test_cross_user_device_scope_protection_works_in_reverse_direction(): void
    {
        $admin =
            $this->createAuthorizedAdmin();


        $userA =
            $this->createUser(
                'A'
            );


        $userB =
            $this->createUser(
                'B'
            );


        $userADeviceId =
            $this->createDevice(
                user:
                    $userA,

                deviceName:
                    'User A Secure Phone'
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
                                $userB,

                            'device' =>
                                $userADeviceId,
                        ]
                    ),
                    [
                        'reason' =>
                            'Reverse cross-user device protection test.',
                    ]
                );


        $response
            ->assertNotFound();


        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $userADeviceId,

                'user_id' =>
                    $userA->id,

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
    | Authorized Admin Fixture
    |--------------------------------------------------------------------------
    */

    private function createAuthorizedAdmin(): AdminUser
    {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'IDOR Security Admin',

                    'email' =>
                        'idor-'
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
                        'IDOR_TEST_'
                        . strtoupper(
                            Str::random(8)
                        ),

                    'name' =>
                        'IDOR Security Test Role',

                    'description' =>
                        'Role used for device IDOR regression testing.',

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);


        $permission =
            AdminPermission::query()
                ->create([
                    'code' =>
                        'users.devices.revoke',

                    'name' =>
                        'Revoke User Devices',

                    'module' =>
                        'USERS',

                    'description' =>
                        'Automated IDOR regression permission.',
                ]);


        $admin
            ->roles()
            ->attach(
                $role->id
            );


        $role
            ->permissions()
            ->attach(
                $permission->id
            );


        return $admin->fresh();
    }


    /*
    |--------------------------------------------------------------------------
    | User Fixture
    |--------------------------------------------------------------------------
    */

    private function createUser(
        string $suffix
    ): User {

        return User::factory()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),

                'whatsapp_number' =>
                    '+94770000'
                    . random_int(
                        100,
                        999
                    ),

                'whatsapp_verified_at' =>
                    now(),

                'referral_code' =>
                    'IDOR'
                    . $suffix
                    . strtoupper(
                        Str::random(6)
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
    | Device Fixture
    |--------------------------------------------------------------------------
    */

    private function createDevice(
        User $user,
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
                'MOBILE',

            'platform' =>
                'ANDROID',

            'device_name' =>
                $deviceName,

            'device_model' =>
                'TradeX Security Test Device',

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
    | Refresh Token Fixture
    |--------------------------------------------------------------------------
    */

    private function createRefreshToken(
        User $user,
        int $deviceId
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
                $now
                    ->copy()
                    ->addDay(),

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
}