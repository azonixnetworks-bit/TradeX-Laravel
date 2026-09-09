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
use Tests\TestCase;

class StrictRoleNavigationTest extends TestCase
{
    use RefreshDatabase;

    private const CUSTOMER_VIEW_MENUS = [
        'Customer Dashboard',
        'All Customers',
        'Pending Onboarding',
        'Pending Accounts',
        'Free Customers',
        'Premium Customers',
        'Suspended / Blocked',
        'Recently Joined',
    ];

    private const KNOWN_MENU_LABELS = [
        'Dashboard',
        'Customer Dashboard',
        'All Customers',
        'Add Customer',
        'Pending Onboarding',
        'Pending Accounts',
        'Free Customers',
        'Premium Customers',
        'Suspended / Blocked',
        'Recently Joined',
        'Education',
        'Our Channel',
        'Notifications',
        'Long-Term Trade Ideas',
        'Daily Trade Ideas',
        'Broker Management',
        'Broker Account Verification',
        'Copy Trade Accounts',
        'Copy Trade Billing',
        'Billing Calendar',
        'Investment Dashboard',
        'All Investments',
        'Add Investment',
        'Deposit Verification',
        'Agreements',
        'Activation Queue',
        'Profit Engine',
        'Settlements',
        'Affiliates',
        'Commission Ledger',
        'Product Catalog',
        'Payments',
        'Bank Deposits',
        'Crypto Deposits',
        'USDT Deposits',
        'Payment Accounts',
        'Payouts',
        'Finance',
        'KYC Verification',
        'Security',
        'Audit Logs',
        'Admin Users',
        'Roles & Permissions',
        'Settings',
        'Integrations',
        'Legal & Policies',
        'Languages',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
    }

    public function test_every_system_role_sidebar_matches_its_granted_navigation_surface(): void
    {
        $customer = self::CUSTOMER_VIEW_MENUS;

        $expectations = [
            'CONTENT_ADMIN' => [
                'Dashboard',
                'Education',
                'Our Channel',
                'Notifications',
            ],
            'TRADE_IDEA_ADMIN' => [
                'Dashboard',
                'Notifications',
                'Long-Term Trade Ideas',
                'Daily Trade Ideas',
            ],
            'COPY_TRADING_ADMIN' => array_merge(
                ['Dashboard'],
                $customer,
                [
                    'Broker Management',
                    'Broker Account Verification',
                    'Copy Trade Accounts',
                    'Copy Trade Billing',
                    'Billing Calendar',
                ]
            ),
            'AFFILIATE_ADMIN' => array_merge(
                ['Dashboard'],
                $customer,
                [
                    'Affiliates',
                    'Commission Ledger',
                ]
            ),
            'INVESTMENT_ADMIN' => array_merge(
                ['Dashboard'],
                $customer,
                [
                    'Investment Dashboard',
                    'All Investments',
                    'Add Investment',
                    'Deposit Verification',
                    'Agreements',
                    'Activation Queue',
                    'Profit Engine',
                    'Settlements',
                ]
            ),
            'FINANCE_ADMIN' => [
                'Dashboard',
                'Copy Trade Billing',
                'Billing Calendar',
                'Investment Dashboard',
                'All Investments',
                'Profit Engine',
                'Settlements',
                'Commission Ledger',
                'Product Catalog',
                'Payments',
                'Bank Deposits',
                'Crypto Deposits',
                'USDT Deposits',
                'Payment Accounts',
                'Payouts',
                'Finance',
            ],
            'KYC_ADMIN' => array_merge(
                ['Dashboard'],
                $customer,
                ['KYC Verification']
            ),
            'SUPPORT_ADMIN' => array_merge(
                ['Dashboard'],
                $customer,
                [
                    'Education',
                    'Our Channel',
                    'Notifications',
                    'Long-Term Trade Ideas',
                    'Daily Trade Ideas',
                    'Affiliates',
                    'Product Catalog',
                    'Payments',
                    'Bank Deposits',
                    'Crypto Deposits',
                    'USDT Deposits',
                ]
            ),
        ];

        foreach ($expectations as $roleCode => $expectedMenus) {
            $admin = $this->adminWithRoles([$roleCode]);
            $this->actingAs($admin, 'admin');

            $this->assertSame(
                $this->orderedKnownMenus($expectedMenus),
                $this->visibleMenus(),
                'Unexpected navigation surface for '.$roleCode
            );
        }
    }

    public function test_super_admin_sees_the_complete_permission_gated_navigation_surface(): void
    {
        $admin = $this->adminWithRoles(['SUPER_ADMIN']);
        $this->actingAs($admin, 'admin');

        $this->assertSame(
            $this->orderedKnownMenus(self::KNOWN_MENU_LABELS),
            $this->visibleMenus()
        );
    }

    public function test_custom_copy_account_role_sees_only_account_navigation_and_routes(): void
    {
        $admin = $this->adminWithCustomPermissions([
            'copy_trading.accounts.view',
        ]);

        $this->actingAs($admin, 'admin');

        $this->assertSame(
            [
                'Broker Account Verification',
                'Copy Trade Accounts',
            ],
            $this->visibleMenus()
        );

        $this->get(route('admin.copy_trading.accounts.index'))
            ->assertOk();

        $this->get(route('admin.copy_trading.brokers.index'))
            ->assertForbidden();

        $this->get(route('admin.copy_trading.billing.index'))
            ->assertForbidden();
    }

    public function test_custom_commission_role_sees_commission_only_and_cannot_open_affiliate_directory(): void
    {
        $admin = $this->adminWithCustomPermissions([
            'commissions.view',
        ]);

        $this->actingAs($admin, 'admin');

        $this->assertSame(
            ['Commission Ledger'],
            $this->visibleMenus()
        );

        $this->get(route('admin.affiliates.commissions.index'))
            ->assertOk();

        $this->get(route('admin.affiliates.index'))
            ->assertForbidden();

        $this->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_dashboard_only_role_has_no_empty_sections_or_sensitive_admin_statistics(): void
    {
        $admin = $this->adminWithCustomPermissions([
            'dashboard.view',
        ]);

        $this->actingAs($admin, 'admin');

        $sidebar = view('admin.layouts.sidebar')->render();

        $this->assertSame(['Dashboard'], $this->visibleMenus($sidebar));

        foreach ([
            'CUSTOMERS',
            'CONTENT',
            'TRADE IDEAS',
            'COPY TRADING',
            'INVESTMENTS',
            'AFFILIATES',
            'FINANCE',
            'SECURITY',
            'SYSTEM',
        ] as $section) {
            $this->assertStringNotContainsString(
                '<div class="nav-section-title">'.$section.'</div>',
                $sidebar
            );
        }

        $response = $this->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertDontSee('Registered administrators');
        $response->assertDontSee('Enabled accounts');
        $response->assertDontSee('RBAC roles configured');
        $response->assertDontSee('Security permissions');
    }

    public function test_multiple_roles_expose_only_the_union_of_their_permissions(): void
    {
        $admin = $this->adminWithRoles([
            'CONTENT_ADMIN',
            'KYC_ADMIN',
        ]);

        $this->actingAs($admin, 'admin');

        $this->assertSame(
            $this->orderedKnownMenus(array_merge(
                ['Dashboard'],
                self::CUSTOMER_VIEW_MENUS,
                [
                    'Education',
                    'Our Channel',
                    'Notifications',
                    'KYC Verification',
                ]
            )),
            $this->visibleMenus()
        );
    }

    private function adminWithRoles(array $roleCodes): AdminUser
    {
        $admin = $this->newAdmin();

        $roleIds = AdminRole::query()
            ->whereIn('code', $roleCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($roleCodes), $roleIds);
        $admin->roles()->sync($roleIds);

        return $admin->fresh('roles');
    }

    private function adminWithCustomPermissions(array $permissionCodes): AdminUser
    {
        $role = AdminRole::query()->create([
            'code' => 'CUSTOM_'.Str::upper(Str::random(12)),
            'name' => 'Custom '.Str::random(8),
            'description' => 'RBAC test role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = AdminPermission::query()
            ->whereIn('code', $permissionCodes)
            ->pluck('id')
            ->all();

        $this->assertCount(count($permissionCodes), $permissionIds);
        $role->permissions()->sync($permissionIds);

        $admin = $this->newAdmin();
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function newAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'RBAC '.Str::random(8),
            'email' => 'rbac-'.Str::lower(Str::random(16)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-RBAC-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }

    private function visibleMenus(?string $sidebar = null): array
    {
        $sidebar ??= view('admin.layouts.sidebar')->render();

        preg_match_all('/<span>([^<]+)<\/span>/', $sidebar, $matches);

        $labels = array_map(
            static fn (string $label): string => trim($label),
            $matches[1] ?? []
        );

        return array_values(array_filter(
            self::KNOWN_MENU_LABELS,
            static fn (string $menu): bool => in_array($menu, $labels, true)
        ));
    }

    private function orderedKnownMenus(array $menus): array
    {
        return array_values(array_filter(
            self::KNOWN_MENU_LABELS,
            static fn (string $menu): bool => in_array($menu, $menus, true)
        ));
    }
}
