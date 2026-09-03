<?php

namespace Tests\Feature\Admin\Investments;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentProduct;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\InvestmentProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInvestmentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        $this->seed(InvestmentProductSeeder::class);
    }

    public function test_investment_foundation_schema_has_no_fixed_annual_return_field(): void
    {
        $this->assertTrue(Schema::hasTable('investment_products'));
        $this->assertTrue(Schema::hasTable('investments'));
        $this->assertTrue(Schema::hasTable('investment_agreements'));
        $this->assertTrue(Schema::hasTable('investment_risk_acceptances'));
        $this->assertTrue(Schema::hasTable('investment_profit_periods'));
        $this->assertTrue(Schema::hasTable('investment_transactions'));
        $this->assertTrue(Schema::hasTable('investment_asset_allocations'));

        $this->assertFalse(
            Schema::hasColumn('investments', 'annual_profit_rate')
        );
        $this->assertFalse(
            Schema::hasColumn('investments', 'guaranteed_profit_percent')
        );

        $this->assertTrue(
            Schema::hasColumn('investments', 'fixed_return_guaranteed')
        );
    }

    public function test_product_catalog_exposes_crypto_and_marks_stock_products_coming_soon(): void
    {
        $this->assertDatabaseHas('investment_products', [
            'code' => InvestmentProduct::CODE_CRYPTO,
            'status' => InvestmentProduct::STATUS_ACTIVE,
            'deposit_currency' => 'USDT',
            'deposit_network' => 'TRC20',
            'customer_profit_share_bps' => 8000,
            'company_profit_share_bps' => 2000,
            'early_exit_return_bps' => 8000,
        ]);

        $this->assertDatabaseHas('investment_products', [
            'code' => InvestmentProduct::CODE_INDIAN_STOCKS,
            'status' => InvestmentProduct::STATUS_COMING_SOON,
        ]);

        $this->assertDatabaseHas('investment_products', [
            'code' => InvestmentProduct::CODE_US_STOCKS,
            'status' => InvestmentProduct::STATUS_COMING_SOON,
        ]);
    }

    public function test_investment_admin_role_has_prepare_but_not_final_payout_approval(): void
    {
        $role = AdminRole::query()
            ->where('code', 'INVESTMENT_ADMIN')
            ->firstOrFail();

        $this->assertTrue(
            $role->hasPermission('investments.payouts.prepare')
        );

        $this->assertFalse(
            $role->hasPermission('investments.payouts.approve')
        );
    }

    public function test_finance_admin_has_final_investment_payout_approval(): void
    {
        $role = AdminRole::query()
            ->where('code', 'FINANCE_ADMIN')
            ->firstOrFail();

        $this->assertTrue(
            $role->hasPermission('investments.payouts.approve')
        );

        $this->assertTrue(
            $role->hasPermission('investments.transactions.verify')
        );
    }

    public function test_investment_admin_can_create_crypto_investment_with_frozen_80_20_model(): void
    {
        $admin = $this->investmentAdmin();

        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.investments.store'), [
                'customer_email' => $user->email,
                'product_code' => InvestmentProduct::CODE_CRYPTO,
                'deposit_amount' => '10000.00000000',
                'investment_period_years' => 2,
                'profit_review_interval_months' => 4,
                'settlement_mode' => Investment::SETTLEMENT_ON_MATURITY,
                'payout_wallet_address' =>
                    'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            ])
            ->assertRedirect();

        $investment = Investment::query()->firstOrFail();

        $this->assertSame(
            Investment::STATUS_AWAITING_DEPOSIT,
            $investment->status
        );
        $this->assertSame(
            Investment::PROFIT_MODEL_PERFORMANCE_SHARE,
            $investment->profit_model
        );
        $this->assertSame(8000, $investment->customer_profit_share_bps);
        $this->assertSame(2000, $investment->company_profit_share_bps);
        $this->assertSame(8000, $investment->early_exit_return_bps);
        $this->assertFalse($investment->fixed_return_guaranteed);
        $this->assertSame('USDT', $investment->deposit_currency);
        $this->assertSame('TRC20', $investment->deposit_network);
        $this->assertSame(2, $investment->investment_period_years);
        $this->assertSame(4, $investment->profit_review_interval_months);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'INVESTMENT_CREATED',
            'module' => 'INVESTMENTS',
            'target_id' => $investment->getKey(),
        ]);
    }

    public function test_coming_soon_stock_product_cannot_accept_investment(): void
    {
        $admin = $this->investmentAdmin();

        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.investments.store'), [
                'customer_email' => $user->email,
                'product_code' =>
                    InvestmentProduct::CODE_INDIAN_STOCKS,
                'deposit_amount' => '10000',
                'investment_period_years' => 1,
                'profit_review_interval_months' => 2,
                'settlement_mode' =>
                    Investment::SETTLEMENT_ON_MATURITY,
            ])
            ->assertSessionHasErrors('product_code');

        $this->assertDatabaseCount('investments', 0);
    }

    private function investmentAdmin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Investment Admin Test',
            'email' => 'investment-admin-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => 'Investment#AdminPassword2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()
            ->where('code', 'INVESTMENT_ADMIN')
            ->firstOrFail();

        $admin->roles()->attach($role->getKey());

        return $admin;
    }
}
