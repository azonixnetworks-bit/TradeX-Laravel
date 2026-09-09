<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Support\Admin\AdminAccessMap;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StrictAccessDependencyAndLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_seeded_system_roles_satisfy_permission_dependencies(): void
    {
        $roles = AdminRole::query()
            ->where('is_system', true)
            ->with('permissions:id,code')
            ->get();

        foreach ($roles as $role) {
            $missing = AdminAccessMap::missingDependencies(
                $role->permissions->pluck('code')->all()
            );

            $this->assertSame(
                [],
                $missing,
                'System role '.$role->code.' has an invalid permission dependency.'
            );
        }
    }

    public function test_custom_role_creation_rejects_action_without_required_view_permission(): void
    {
        $owner = $this->superAdmin();

        $dashboard = AdminPermission::query()
            ->where('code', 'dashboard.view')
            ->firstOrFail();
        $verify = AdminPermission::query()
            ->where('code', 'payments.verify')
            ->firstOrFail();

        $this->actingAs($owner, 'admin')
            ->post(route('admin.administration.roles.store'), [
                'code' => 'BROKEN_PAYMENT_ROLE',
                'name' => 'Broken Payment Role',
                'description' => 'Dependency regression test.',
                'permission_ids' => [
                    $dashboard->id,
                    $verify->id,
                ],
            ])
            ->assertSessionHasErrors('permission_ids');

        $this->assertDatabaseMissing('admin_roles', [
            'code' => 'BROKEN_PAYMENT_ROLE',
        ]);
    }

    public function test_custom_role_creation_accepts_action_with_required_view_permission(): void
    {
        $owner = $this->superAdmin();

        $permissions = AdminPermission::query()
            ->whereIn('code', [
                'dashboard.view',
                'payments.view',
                'payments.verify',
            ])
            ->pluck('id')
            ->all();

        $this->actingAs($owner, 'admin')
            ->post(route('admin.administration.roles.store'), [
                'code' => 'VALID_PAYMENT_ROLE',
                'name' => 'Valid Payment Role',
                'description' => 'Dependency regression test.',
                'permission_ids' => $permissions,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('admin_roles', [
            'code' => 'VALID_PAYMENT_ROLE',
            'is_system' => false,
        ]);
    }

    public function test_password_login_lands_on_first_granted_module_without_dashboard_permission(): void
    {
        config()->set('tradex_admin_hardening.totp.required', false);

        $permission = AdminPermission::query()
            ->where('code', 'market_clock.view')
            ->firstOrFail();

        $role = AdminRole::query()->create([
            'code' => 'MARKET_CLOCK_ONLY',
            'name' => 'Market Clock Only',
            'description' => 'Landing route regression role.',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Market Clock Admin',
            'email' => 'market-clock-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'MarketClock#Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
        $admin->roles()->sync([$role->id]);

        $this->post(route('admin.login.submit'), [
            'email' => $admin->email,
            'password' => 'MarketClock#Password2026!',
        ])->assertRedirect(route('admin.market_clock.index'));

        $this->assertAuthenticatedAs($admin, 'admin');
    }

    public function test_logged_in_admin_login_page_redirects_to_first_granted_module(): void
    {
        $permission = AdminPermission::query()
            ->where('code', 'screener.monitor.view')
            ->firstOrFail();

        $role = AdminRole::query()->create([
            'code' => 'SCREENER_ONLY',
            'name' => 'Screener Only',
            'description' => 'Landing route regression role.',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Screener Admin',
            'email' => 'screener-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Screener#Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
        $admin->roles()->sync([$role->id]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.login'))
            ->assertRedirect(route('admin.screener.index'));
    }

    public function test_admin_root_redirects_to_first_granted_module(): void
    {
        $permission = AdminPermission::query()
            ->where('code', 'market_clock.view')
            ->firstOrFail();

        $role = AdminRole::query()->create([
            'code' => 'ROOT_MARKET_CLOCK_ONLY',
            'name' => 'Root Market Clock Only',
            'description' => 'Admin root landing regression role.',
            'is_system' => false,
            'is_active' => true,
        ]);
        $role->permissions()->sync([$permission->id]);

        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Admin Root Test',
            'email' => 'admin-root-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'AdminRoot#Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
        $admin->roles()->sync([$role->id]);

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertRedirect(route('admin.market_clock.index'));
    }

    private function superAdmin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'RBAC Owner Test',
            'email' => 'rbac-owner-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Owner#Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()
            ->where('code', 'SUPER_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
