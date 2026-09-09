<?php

namespace Tests\Feature\Admin\Administration;

use App\Models\AdminRole;
use App\Models\AdminUser;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class FinanceAdminNavigationScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_finance_admin_role_is_scoped_to_finance_related_permissions(): void
    {
        $role = AdminRole::query()
            ->where('code', 'FINANCE_ADMIN')
            ->firstOrFail();

        $codes = $role->permissions()
            ->pluck('code')
            ->all();

        foreach ([
            'dashboard.view',
            'products.view',
            'payments.view',
            'payment_accounts.manage',
            'finance.view',
            'finance.reports',
            'investments.view',
            'investments.profit_periods.approve',
            'payouts.view',
        ] as $expected) {
            $this->assertContains($expected, $codes);
        }

        foreach ([
            'users.view',
            'users.view_details',
            'market_clock.view',
            'screener.monitor.view',
            'education.view',
            'trade_ideas.view',
            'copy_trading.view',
            'affiliates.view',
            'notifications.view',
            'kyc.view',
            'admins.view',
            'settings.view',
            'investments.activate',
        ] as $forbidden) {
            $this->assertNotContains($forbidden, $codes);
        }
    }

    public function test_finance_admin_sidebar_hides_unrelated_modules(): void
    {
        $admin = $this->financeAdmin();

        $this->actingAs($admin, 'admin');

        $sidebar = view('admin.layouts.sidebar')->render();

        // This test renders the application layout directly instead of through
        // Laravel's web middleware stack. Supply the empty view error bag that
        // ShareErrorsFromSession normally injects for real HTTP requests.
        $layout = view('admin.layouts.app', [
            'errors' => new ViewErrorBag(),
        ])->render();

        foreach ([
            'Dashboard',
            'Product Catalog',
            'Payments',
            'Payment Accounts',
            'Payouts',
            'Finance',
            'Investment Dashboard',
            'Profit Engine',
            'Settlements',
        ] as $visible) {
            $this->assertStringContainsString($visible, $sidebar);
        }

        foreach ([
            'Customer Dashboard',
            'All Customers',
            'Add Customer',
            'Education',
            'Our Channel',
            'Long-Term Trade Ideas',
            'Daily Trade Ideas',
            'Broker Management',
            'Affiliates',
            'Notifications',
            'KYC',
            'Admin Users',
            'Activation Queue',
        ] as $hidden) {
            $this->assertStringNotContainsString($hidden, $sidebar);
        }

        $this->assertStringNotContainsString(
            'admin/js/market-clock-nav.js',
            $layout
        );
        $this->assertStringNotContainsString(
            'admin/js/screener-monitor-nav.js',
            $layout
        );
    }

    public function test_finance_admin_cannot_open_customer_or_operational_monitor_routes_directly(): void
    {
        $admin = $this->financeAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.market_clock.index'))
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.screener.index'))
            ->assertForbidden();
    }

    private function financeAdmin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Finance Admin Test',
            'email' => 'finance-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Finance-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()
            ->where('code', 'FINANCE_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
