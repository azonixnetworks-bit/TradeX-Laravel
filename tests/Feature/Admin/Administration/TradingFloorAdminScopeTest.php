<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\TradingFloorPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TradingFloorAdminScopeTest extends TestCase
{
    use RefreshDatabase;

    private function floorAdmin(): AdminUser
    {
        $this->seed([AdminRoleSeeder::class, AdminPermissionSeeder::class, AdminRolePermissionSeeder::class]);
        $admin = AdminUser::create([
            'uuid' => (string) Str::uuid(), 'name' => 'Floor Admin',
            'email' => 'floor@example.test', 'password' => 'Test-password-123!',
            'email_verified_at' => now(), 'is_active' => true, 'failed_login_attempts' => 0,
        ]);
        $admin->roles()->sync([AdminRole::where('code', 'TRADING_FLOOR_ADMIN')->firstOrFail()->id]);

        return $admin;
    }

    public function test_floor_role_is_idempotent_and_contains_only_floor_permissions(): void
    {
        $admin = $this->floorAdmin();
        $this->seed(TradingFloorPermissionSeeder::class);
        $role = $admin->roles()->firstOrFail();
        $this->assertSame(14, $role->permissions()->count());
        foreach ($role->permissions as $permission) {
            $this->assertStringStartsWith('trading_floor.', $permission->code);
        }
        $this->assertSame(1, AdminRole::where('code', 'TRADING_FLOOR_ADMIN')->count());
    }

    public function test_floor_admin_lands_on_floor_and_can_use_floor_pages(): void
    {
        $this->actingAs($this->floorAdmin(), 'admin');
        $this->get('/admin')->assertRedirect(route('admin.trading_floor.index'));
        foreach (['', '/clients', '/clients/create', '/memberships', '/resources', '/access', '/commissions', '/reports', '/reminders', '/access/settings'] as $path) {
            $this->get('/admin/trading-floor'.$path)->assertOk();
        }
    }

    public function test_unrelated_pages_and_mutations_are_forbidden_and_menus_hidden(): void
    {
        $this->actingAs($this->floorAdmin(), 'admin');
        foreach (['dashboard', 'users', 'products', 'economic-news', 'market-clock', 'screener', 'administration/admins', 'administration/roles', 'settings/general', 'payments', 'investments', 'kyc'] as $path) {
            $this->get('/admin/'.$path)->assertForbidden();
        }
        $this->post('/admin/products', [])->assertForbidden();
        $this->post('/admin/administration/admins', [])->assertForbidden();
        $sidebar = view('admin.layouts.sidebar')->render();
        $this->assertStringContainsString('Floor Dashboard', $sidebar);
        foreach (['All Customers', 'Product Catalog', 'Economic Calendar', 'Admin Users', 'Roles &amp; Permissions', 'Open Market Clock'] as $menu) {
            $this->assertStringNotContainsString($menu, $sidebar);
        }
    }
}
