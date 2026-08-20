<?php

namespace Tests\Feature\Admin;

use App\Http\Requests\Admin\Users\UserDeviceRevokeRequest;
use App\Http\Requests\Admin\Users\UserStatusActionRequest;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminRbacDenialTest extends TestCase
{
    use RefreshDatabase;


    /*
    |--------------------------------------------------------------------------
    | USERS.VIEW
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_users_view_cannot_open_users_index(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.users.index'
                    )
                );


        $response->assertForbidden();
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.VIEW_DETAILS
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_users_view_details_cannot_open_user_details(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser();


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.users.show',
                        $user
                    )
                );


        $response->assertForbidden();
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.ACTIVATE
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_users_activate_cannot_activate_user(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser(
                status: 'SUSPENDED'
            );


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.status.update',
                        $user
                    ),
                    [
                        'action' =>
                            'ACTIVATE',

                        'reason' =>
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertSame(
            'SUSPENDED',
            $user
                ->fresh()
                ->status
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.SUSPEND
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_users_suspend_cannot_suspend_user(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser(
                status: 'ACTIVE'
            );


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.status.update',
                        $user
                    ),
                    [
                        'action' =>
                            'SUSPEND',

                        'reason' =>
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertSame(
            'ACTIVE',
            $user
                ->fresh()
                ->status
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.BLOCK
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_users_block_cannot_block_user(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser(
                status: 'ACTIVE'
            );


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->patch(
                    route(
                        'admin.users.status.update',
                        $user
                    ),
                    [
                        'action' =>
                            'BLOCK',

                        'reason' =>
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertSame(
            'ACTIVE',
            $user
                ->fresh()
                ->status
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.DEVICES.REVOKE
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_device_revoke_permission_cannot_revoke_device(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser();

        $deviceId =
            $this->createDevice(
                $user
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
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $deviceId,

                'user_id' =>
                    $user->id,

                'is_active' =>
                    1,

                'revoked_at' =>
                    null,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.DEVICES.RESET
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_device_reset_permission_cannot_reset_device_slots(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser();

        $deviceId =
            $this->createDevice(
                $user
            );


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
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertDatabaseHas(
            'user_devices',
            [
                'id' =>
                    $deviceId,

                'user_id' =>
                    $user->id,

                'is_active' =>
                    1,

                'revoked_at' =>
                    null,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | USERS.SESSIONS.REVOKE
    |--------------------------------------------------------------------------
    */

    public function test_admin_without_session_revoke_permission_cannot_revoke_sessions(): void
    {
        $admin =
            $this->createAdminWithPermissions();

        $user =
            $this->createUser();

        $deviceId =
            $this->createDevice(
                $user
            );

        $refreshTokenId =
            $this->createRefreshToken(
                user:
                    $user,

                deviceId:
                    $deviceId
            );


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
                            'RBAC denial regression test.',
                    ]
                );


        $response->assertForbidden();


        $this->assertDatabaseHas(
            'user_refresh_tokens',
            [
                'id' =>
                    $refreshTokenId,

                'user_id' =>
                    $user->id,

                'revoked_at' =>
                    null,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | EXACT PERMISSION — POSITIVE CHECK
    |--------------------------------------------------------------------------
    */

    public function test_admin_receives_only_permissions_assigned_to_role(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                [
                    'users.view',
                ]
            );


        $this->assertTrue(
            $admin->hasPermission(
                'users.view'
            )
        );


        $this->assertFalse(
            $admin->hasPermission(
                'users.block'
            )
        );


        $this->assertFalse(
            $admin->hasPermission(
                'users.devices.reset'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DEVICE REVOKE REQUEST — POSITIVE AUTHORIZATION
    |--------------------------------------------------------------------------
    |
    | This test prevents regression back to:
    |
    | authorize(): false
    |
    */

    public function test_device_revoke_request_authorizes_admin_with_exact_permission(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                [
                    'users.devices.revoke',
                ]
            );


        $this->actingAs(
            $admin,
            'admin'
        );


        $request =
            UserDeviceRevokeRequest::create(
                '/admin/test-device-revoke',
                'PATCH',
                [
                    'reason' =>
                        'Authorized device revoke test.',
                ]
            );


        $this->assertTrue(
            $request->authorize()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS REQUEST — EXACT ACTION PERMISSION
    |--------------------------------------------------------------------------
    */

    public function test_status_request_requires_permission_for_exact_action(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                [
                    'users.suspend',
                ]
            );


        $this->actingAs(
            $admin,
            'admin'
        );


        $suspendRequest =
            UserStatusActionRequest::create(
                '/admin/test-status',
                'PATCH',
                [
                    'action' =>
                        'SUSPEND',

                    'reason' =>
                        'Authorized suspend regression test.',
                ]
            );


        $blockRequest =
            UserStatusActionRequest::create(
                '/admin/test-status',
                'PATCH',
                [
                    'action' =>
                        'BLOCK',

                    'reason' =>
                        'Unauthorized block regression test.',
                ]
            );


        $this->assertTrue(
            $suspendRequest->authorize()
        );


        $this->assertFalse(
            $blockRequest->authorize()
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INACTIVE ROLE
    |--------------------------------------------------------------------------
    */

    public function test_permission_from_inactive_role_is_not_granted(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                [
                    'users.view',
                ]
            );


        $role =
            $admin
                ->roles()
                ->firstOrFail();


        $role->update([
            'is_active' =>
                false,
        ]);


        $this->assertFalse(
            $admin
                ->fresh()
                ->hasPermission(
                    'users.view'
                )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | SUPER ADMIN OVERRIDE
    |--------------------------------------------------------------------------
    */

    public function test_super_admin_has_full_permission_override(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                permissions: [],
                roleCode: 'SUPER_ADMIN'
            );


        $this->assertTrue(
            $admin->isSuperAdmin()
        );


        $this->assertTrue(
            $admin->hasPermission(
                'users.view'
            )
        );


        $this->assertTrue(
            $admin->hasPermission(
                'users.devices.revoke'
            )
        );


        $this->assertTrue(
            $admin->hasPermission(
                'users.sessions.revoke'
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INACTIVE ADMIN SESSION
    |--------------------------------------------------------------------------
    */

    public function test_inactive_admin_is_removed_from_protected_admin_session(): void
    {
        $admin =
            $this->createAdminWithPermissions(
                permissions: [
                    'users.view',
                ],
                isActive: false
            );


        $response =
            $this
                ->actingAs(
                    $admin,
                    'admin'
                )
                ->get(
                    route(
                        'admin.users.index'
                    )
                );


        $response->assertRedirect(
            route(
                'admin.login'
            )
        );


        $this->assertGuest(
            'admin'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Test Fixture — Admin + Role + Permissions
    |--------------------------------------------------------------------------
    */

    private function createAdminWithPermissions(
        array $permissions = [],
        bool $isActive = true,
        ?string $roleCode = null
    ): AdminUser {

        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'RBAC Test Admin',

                    'email' =>
                        'admin-'
                        . Str::lower(
                            Str::random(12)
                        )
                        . '@tradex.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'TestingPassword123!',

                    'is_active' =>
                        $isActive,

                    'failed_login_attempts' =>
                        0,

                    'locked_until' =>
                        null,
                ]);


        if (
            $permissions === []
            &&
            $roleCode === null
        ) {
            return $admin;
        }


        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        $roleCode
                        ?? (
                            'TEST_'
                            . strtoupper(
                                Str::random(10)
                            )
                        ),

                    'name' =>
                        'RBAC Test Role',

                    'description' =>
                        'Automated RBAC regression role.',

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
            $permissions
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
                            'Automated RBAC regression permission.',
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
    | Test Fixture — User
    |--------------------------------------------------------------------------
    */

    private function createUser(
        string $status = 'ACTIVE'
    ): User {

        return User::factory()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),

                'whatsapp_number' =>
                    '+94770000000',

                'whatsapp_verified_at' =>
                    now(),

                'referral_code' =>
                    'TST'
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
                    $status,
            ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Test Fixture — Trusted Mobile Device
    |--------------------------------------------------------------------------
    */

    private function createDevice(
        User $user
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
                'RBAC Test Phone',

            'device_model' =>
                'Test Device',

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
    | Test Fixture — Active Refresh Token
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