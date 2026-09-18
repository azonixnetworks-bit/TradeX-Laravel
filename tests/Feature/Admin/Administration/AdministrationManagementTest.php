<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_guest_is_redirected_from_admin_user_index(): void
    {
        $this->get(
            route('admin.administration.admins.index')
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_admin_user_index_requires_admins_view(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(
                route('admin.administration.admins.index')
            )
            ->assertForbidden();
    }

    public function test_admin_user_index_is_visible_with_permission(): void
    {
        $admin = $this->adminWithPermissions([
            'admins.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(
                route('admin.administration.admins.index')
            )
            ->assertOk()
            ->assertSee($admin->email);
    }

    public function test_create_admin_requires_create_and_role_manage_permissions(): void
    {
        $admin = $this->adminWithPermissions([
            'admins.create',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(
                route('admin.administration.admins.create')
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_delegated_admin_without_super_admin_role(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.create',
            'roles.manage',
            'admins.view',
        ]);

        $role = AdminRole::query()
            ->where('code', 'SUPPORT_ADMIN')
            ->firstOrFail();

        $response = $this->actingAs($actor, 'admin')
            ->post(
                route('admin.administration.admins.store'),
                [
                    'name' => 'Support Operator',
                    'email' => 'support.operator@example.test',
                    'password' => 'Strong-Admin-Pass-123!',
                    'password_confirmation' => 'Strong-Admin-Pass-123!',
                    'role_ids' => [$role->id],
                ]
            );

        $created = AdminUser::query()
            ->where(
                'email',
                'support.operator@example.test'
            )
            ->firstOrFail();

        $response->assertRedirect(
            route(
                'admin.administration.admins.edit',
                $created
            )
        );

        $this->assertTrue(
            $created->roles()
                ->where(
                    'code',
                    'SUPPORT_ADMIN'
                )
                ->exists()
        );

        $this->assertFalse(
            $created->roles()
                ->where(
                    'code',
                    'SUPER_ADMIN'
                )
                ->exists()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'ADMIN_ACCOUNT_CREATED',
                'target_id' =>
                    $created->id,
            ]
        );
    }

    public function test_super_admin_role_cannot_be_assigned_through_admin_create(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.create',
            'roles.manage',
        ]);

        $superRole = AdminRole::query()
            ->where('code', 'SUPER_ADMIN')
            ->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->post(
                route('admin.administration.admins.store'),
                [
                    'name' => 'Invalid Super',
                    'email' => 'invalid.super@example.test',
                    'password' => 'Strong-Admin-Pass-123!',
                    'password_confirmation' => 'Strong-Admin-Pass-123!',
                    'role_ids' => [$superRole->id],
                ]
            )
            ->assertSessionHasErrors(
                'role_ids.0'
            );

        $this->assertDatabaseMissing(
            'admin_users',
            [
                'email' =>
                    'invalid.super@example.test',
            ]
        );
    }

    public function test_super_admin_account_cannot_be_updated_through_admin_13(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.update',
        ]);

        $target = $this->seededSuperAdmin();

        $this->actingAs($actor, 'admin')
            ->patch(
                route(
                    'admin.administration.admins.update',
                    $target
                ),
                [
                    'name' => 'Changed Owner',
                    'email' => 'changed.owner@example.test',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'Owner Admin',
            $target->fresh()->name
        );
    }

    public function test_admin_cannot_change_own_status(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.disable',
        ]);

        $this->actingAs($actor, 'admin')
            ->patch(
                route(
                    'admin.administration.admins.status',
                    $actor
                ),
                [
                    'status' => 'DISABLED',
                    'reason' =>
                        'Attempt self disable',
                ]
            )
            ->assertForbidden();

        $this->assertTrue(
            $actor->fresh()->is_active
        );
    }

    public function test_authorized_admin_can_disable_another_delegated_admin(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.disable',
        ]);

        $target = $this->plainAdmin();

        $this->actingAs($actor, 'admin')
            ->patch(
                route(
                    'admin.administration.admins.status',
                    $target
                ),
                [
                    'status' => 'DISABLED',
                    'reason' =>
                        'Access no longer required',
                ]
            )
            ->assertRedirect();

        $this->assertFalse(
            $target->fresh()->is_active
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'ADMIN_ACCOUNT_DISABLED',
                'target_id' =>
                    $target->id,
            ]
        );
    }

    public function test_role_sync_requires_both_admin_update_and_roles_manage(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.update',
        ]);

        $target = $this->plainAdmin();

        $role = AdminRole::query()
            ->where('code', 'CONTENT_ADMIN')
            ->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->patch(
                route(
                    'admin.administration.admins.roles',
                    $target
                ),
                [
                    'role_ids' =>
                        [$role->id],
                ]
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_sync_delegated_roles(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.update',
            'roles.manage',
        ]);

        $target = $this->plainAdmin();

        $role = AdminRole::query()
            ->where('code', 'CONTENT_ADMIN')
            ->firstOrFail();

        $this->actingAs($actor, 'admin')
            ->patch(
                route(
                    'admin.administration.admins.roles',
                    $target
                ),
                [
                    'role_ids' =>
                        [$role->id],
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            ['CONTENT_ADMIN'],
            $target->fresh()
                ->roles()
                ->pluck('code')
                ->all()
        );
    }

    public function test_role_index_requires_roles_view(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(
                route('admin.administration.roles.index')
            )
            ->assertForbidden();
    }

    public function test_system_role_permission_page_is_read_only(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.view',
            'roles.manage',
        ]);

        $role = AdminRole::query()
            ->where('code', 'FINANCE_ADMIN')
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.administration.roles.permissions',
                    $role
                )
            )
            ->assertOk()
            ->assertSee(
                'Immutable system role'
            );
    }

    public function test_authorized_admin_can_create_custom_role(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.manage',
            'roles.view',
        ]);

        $permission = AdminPermission::query()
            ->where('code', 'users.view')
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.administration.roles.store'),
                [
                    'code' => 'CUSTOM_SUPPORT',
                    'name' => 'Custom Support',
                    'description' => 'Limited support.',
                    'permission_ids' =>
                        [$permission->id],
                ]
            )
            ->assertRedirect();

        $role = AdminRole::query()
            ->where(
                'code',
                'CUSTOM_SUPPORT'
            )
            ->firstOrFail();

        $this->assertFalse(
            $role->is_system
        );

        $this->assertTrue(
            $role->permissions()
                ->where(
                    'code',
                    'users.view'
                )
                ->exists()
        );
    }

    public function test_super_admin_custom_role_creation_is_blocked_by_validation(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.manage',
        ]);

        $permission =
            AdminPermission::query()
                ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.administration.roles.store'),
                [
                    'code' => 'SUPER_ADMIN',
                    'name' => 'Fake Super Admin',
                    'permission_ids' =>
                        [$permission->id],
                ]
            )
            ->assertSessionHasErrors(
                'code'
            );
    }

    public function test_system_role_mutation_is_blocked(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.manage',
        ]);

        $role = AdminRole::query()
            ->where(
                'code',
                'CONTENT_ADMIN'
            )
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.administration.roles.update',
                    $role
                ),
                [
                    'name' =>
                        'Tampered Content Admin',
                    'description' =>
                        'Should not change.',
                ]
            )
            ->assertForbidden();

        $this->assertSame(
            'Content Admin',
            $role->fresh()->name
        );
    }

    public function test_custom_role_permissions_can_be_changed(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.manage',
        ]);

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'CUSTOM_AUDITOR',
                'name' =>
                    'Custom Auditor',
                'description' =>
                    null,
                'is_system' =>
                    false,
                'is_active' =>
                    true,
            ]);

        $permission = AdminPermission::query()
            ->where('code', 'audit.view')
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.administration.roles.permissions.update',
                    $role
                ),
                [
                    'permission_ids' =>
                        [$permission->id],
                ]
            )
            ->assertRedirect();

        $this->assertTrue(
            $role->permissions()
                ->where(
                    'code',
                    'audit.view'
                )
                ->exists()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'ADMIN_ROLE_PERMISSIONS_CHANGED',
                'target_id' =>
                    $role->id,
            ]
        );
    }

    public function test_permission_catalog_is_read_only(): void
    {
        $admin = $this->adminWithPermissions([
            'roles.view',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(
                route(
                    'admin.administration.permissions.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'Permission Catalog'
            );
    }

    public function test_admin_13_has_no_delete_routes(): void
    {
        $routes = collect(
            app('router')
                ->getRoutes()
                ->getRoutes()
        )
            ->filter(
                fn ($route): bool =>
                    str_starts_with(
                        (string) $route->uri(),
                        'admin/administration'
                    )
            );

        $this->assertCount(
            17, // Includes Super Admin device listing and access updates.
            $routes
        );

        foreach ($routes as $route) {
            $this->assertNotContains(
                'DELETE',
                $route->methods()
            );
        }
    }

    public function test_password_is_never_copied_into_admin_creation_audit_payload(): void
    {
        $actor = $this->adminWithPermissions([
            'admins.create',
            'roles.manage',
        ]);

        $role = AdminRole::query()
            ->where('code', 'SUPPORT_ADMIN')
            ->firstOrFail();

        $password = 'Strong-Admin-Pass-123!';

        $this->actingAs($actor, 'admin')
            ->post(
                route('admin.administration.admins.store'),
                [
                    'name' => 'Audit Safe Admin',
                    'email' => 'audit.safe@example.test',
                    'password' => $password,
                    'password_confirmation' => $password,
                    'role_ids' => [$role->id],
                ]
            )
            ->assertRedirect();

        $audit = AuditLog::query()
            ->where(
                'action',
                'ADMIN_ACCOUNT_CREATED'
            )
            ->latest('id')
            ->firstOrFail();

        $payload = json_encode(
            $audit->new_values,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $password,
            $payload
        );

        $this->assertStringNotContainsString(
            'password',
            strtolower($payload)
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),
                'name' =>
                    'Administration Test Actor',
                'email' =>
                    'admin13-'
                    .Str::lower(Str::random(10))
                    .'@example.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'Testing-Admin-Pass-123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'ADMIN13_TEST_'
                    .Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'ADMIN-13 Test Role',
                'description' =>
                    null,
                'is_system' =>
                    false,
                'is_active' =>
                    true,
            ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->where('code', $code)
                    ->firstOrFail();

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()
            ->sync($permissionIds);

        $admin->roles()
            ->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function plainAdmin(): AdminUser
    {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),
                'name' =>
                    'Delegated Admin',
                'email' =>
                    'delegated-'
                    .Str::lower(Str::random(10))
                    .'@example.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'Testing-Admin-Pass-123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);

        $role = AdminRole::query()
            ->where('code', 'SUPPORT_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin;
    }

    private function seededSuperAdmin(): AdminUser
    {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),
                'name' =>
                    'Owner Admin',
                'email' =>
                    'owner-'
                    .Str::lower(Str::random(8))
                    .'@example.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'Testing-Owner-Pass-123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);

        $role = AdminRole::query()
            ->where('code', 'SUPER_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin;
    }
}
