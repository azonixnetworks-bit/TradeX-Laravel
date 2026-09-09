<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class OperationalNavigationPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_market_clock_permission_loads_only_market_clock_navigation_asset_without_dashboard_permission(): void
    {
        $admin = $this->adminWithPermissions(['market_clock.view']);
        $this->actingAs($admin, 'admin');

        $layout = $this->layout();

        $this->assertStringContainsString('admin/js/market-clock-nav.js', $layout);
        $this->assertStringNotContainsString('admin/js/screener-monitor-nav.js', $layout);
        $this->assertStringNotContainsString('>Dashboard</span>', $layout);

        $this->get(route('admin.market_clock.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_screener_permission_loads_only_screener_navigation_asset_without_dashboard_permission(): void
    {
        $admin = $this->adminWithPermissions(['screener.monitor.view']);
        $this->actingAs($admin, 'admin');

        $layout = $this->layout();

        $this->assertStringContainsString('admin/js/screener-monitor-nav.js', $layout);
        $this->assertStringNotContainsString('admin/js/market-clock-nav.js', $layout);
        $this->assertStringNotContainsString('>Dashboard</span>', $layout);

        $this->get(route('admin.screener.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_operational_navigation_javascript_has_a_dashboard_independent_overview_fallback(): void
    {
        $market = file_get_contents(public_path('admin/js/market-clock-nav.js'));
        $screener = file_get_contents(public_path('admin/js/screener-monitor-nav.js'));

        $this->assertIsString($market);
        $this->assertIsString($screener);

        $this->assertStringContainsString("title.textContent.trim() === 'OVERVIEW'", $market);
        $this->assertStringContainsString('nav.prepend(overviewTitle)', $market);
        $this->assertStringContainsString("title.textContent.trim() === 'OVERVIEW'", $screener);
        $this->assertStringContainsString('marketClockLink || dashboardLink || overviewTitle', $screener);
    }

    private function adminWithPermissions(array $permissionCodes): AdminUser
    {
        $role = AdminRole::query()->create([
            'code' => 'OPS_'.Str::upper(Str::random(12)),
            'name' => 'Ops '.Str::random(8),
            'description' => 'Operational navigation test role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = AdminPermission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionCodes), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Ops Admin',
            'email' => 'ops-'.Str::lower(Str::random(16)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Ops-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function layout(): string
    {
        return view('admin.layouts.app', [
            'errors' => new ViewErrorBag(),
        ])->render();
    }
}
