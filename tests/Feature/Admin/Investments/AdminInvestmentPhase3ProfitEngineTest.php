<?php

namespace Tests\Feature\Admin\Investments;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentAssetAllocation;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProduct;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTrade;
use App\Models\InvestmentTransaction;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\InvestmentProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInvestmentPhase3ProfitEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        $this->seed(InvestmentProductSeeder::class);
    }

    public function test_phase3_schema_and_maker_checker_permissions_are_present(): void
    {
        foreach ([
            'investment_trades',
            'investment_approved_costs',
            'investment_evidences',
            'investment_financial_adjustments',
            'investment_asset_movements',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), $table);
        }

        $this->assertTrue(Schema::hasColumns('investment_profit_periods', [
            'distribution_outcome',
            'rounding_adjustment_usdt',
            'calculation_version',
            'calculation_source_sha256',
            'calculation_snapshot',
        ]));

        $investmentRole = AdminRole::where('code', 'INVESTMENT_ADMIN')->firstOrFail();
        $financeRole = AdminRole::where('code', 'FINANCE_ADMIN')->firstOrFail();

        $this->assertTrue($investmentRole->hasPermission('investments.trades.record'));
        $this->assertTrue($investmentRole->hasPermission('investments.profit_periods.prepare'));
        $this->assertFalse($investmentRole->hasPermission('investments.profit_periods.approve'));
        $this->assertTrue($financeRole->hasPermission('investments.trades.verify'));
        $this->assertTrue($financeRole->hasPermission('investments.profit_periods.approve'));
    }

    public function test_arbitrary_spot_coin_trade_requires_verified_screenshot_before_finance_verification(): void
    {
        [$manager, $finance, $investment, $period] = $this->fixture();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.trades.store', $investment), $this->tradePayload('BUY', 'PEPE', '0'))
            ->assertRedirect();

        $trade = InvestmentTrade::firstOrFail();
        $this->assertSame(InvestmentTrade::INSTRUMENT_SPOT, $trade->instrument_type);
        $this->assertSame('PEPE', $trade->asset_symbol);

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.trades.verify', [$investment, $trade]))
            ->assertSessionHasErrors('trade');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.evidence.trade.store', [$investment, $trade]), [
                'document' => UploadedFile::fake()->createWithContent('trade.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
                'source_reference' => 'SCREENSHOT-PEPE-001',
            ])->assertRedirect();

        $evidence = InvestmentEvidence::firstOrFail();
        $this->assertSame(64, strlen($evidence->document_sha256));
        Storage::disk('private')->assertExists($evidence->document_path);

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.evidence.verify', [$investment, $evidence]))
            ->assertRedirect();

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.trades.verify', [$investment, $trade]))
            ->assertRedirect();

        $trade->refresh();
        $this->assertSame(InvestmentTrade::STATUS_VERIFIED, $trade->status);
        $this->assertDatabaseHas('investment_asset_allocations', [
            'investment_id' => $investment->getKey(),
            'source_trade_id' => $trade->getKey(),
            'asset_symbol' => 'PEPE',
            'status' => InvestmentAssetAllocation::STATUS_ACTIVE,
        ]);
    }

    public function test_only_frozen_direct_trading_cost_categories_are_accepted(): void
    {
        [$manager, , $investment] = $this->fixture();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.costs.store', $investment), [
                'cost_type' => 'SALARY',
                'amount_usdt' => '100',
                'occurred_at' => now()->subDays(2)->toDateTimeString(),
                'source_reference' => 'SALARY-001',
            ])->assertSessionHasErrors('cost_type');

        $this->assertDatabaseCount('investment_approved_costs', 0);
    }

    public function test_verified_top_up_increases_hwm_without_resetting_loss_history(): void
    {
        [$manager, $finance, $investment] = $this->fixture(lossCarry: '250.00000000');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.topups.store', $investment), [
                'amount' => '2000',
                'txid' => str_repeat('a', 64),
                'receiving_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                'occurred_at' => now()->subDays(2)->toDateTimeString(),
            ])->assertRedirect();

        $topUp = InvestmentTransaction::where('type', InvestmentTransaction::TYPE_TOP_UP)->firstOrFail();

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.topups.verify', [$investment, $topUp]))
            ->assertRedirect();

        $investment->refresh();
        $this->assertSame('12000.00000000', $investment->high_water_mark_usdt);
        $this->assertSame('250.00000000', $investment->loss_carryforward_usdt);
    }

    public function test_loss_carryforward_must_be_recovered_before_any_80_20_split(): void
    {
        [$manager, $finance, $investment, $period1, $period2] = $this->twoPeriodFixture();

        $this->verifiedTrade($investment, $period1, '-2000', 'LOSS');
        $this->verifiedTrade($investment, $period2, '1000', 'RECOVERY');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.profit_periods.prepare', [$investment, $period1]))
            ->assertRedirect();
        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.profit_periods.approve', [$investment, $period1]))
            ->assertRedirect();

        $period1->refresh();
        $this->assertSame(InvestmentProfitPeriod::OUTCOME_LOSS_CARRIED_FORWARD, $period1->distribution_outcome);
        $this->assertSame('2000.00000000', $period1->loss_carryforward_close_usdt);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.profit_periods.prepare', [$investment, $period2]))
            ->assertRedirect();

        $period2->refresh();
        $this->assertSame('0.00000000', $period2->eligible_net_profit_usdt);
        $this->assertSame('1000.00000000', $period2->loss_carryforward_close_usdt);
        $this->assertSame('0.00000000', $period2->customer_profit_share_usdt);
        $this->assertSame('0.00000000', $period2->company_profit_share_usdt);
    }

    public function test_positive_realized_profit_less_verified_costs_splits_80_20(): void
    {
        [$manager, , $investment, $period] = $this->fixture();
        $this->verifiedTrade($investment, $period, '2000', 'PROFIT');
        $this->verifiedCost($investment, $period, '100');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.profit_periods.prepare', [$investment, $period]))
            ->assertRedirect();

        $period->refresh();
        $this->assertSame(InvestmentProfitPeriod::STATUS_APPROVAL_PENDING, $period->status);
        $this->assertSame(InvestmentProfitPeriod::OUTCOME_PAYABLE, $period->distribution_outcome);
        $this->assertSame('1900.00000000', $period->eligible_net_profit_usdt);
        $this->assertSame('1520.00000000', $period->customer_profit_share_usdt);
        $this->assertSame('380.00000000', $period->company_profit_share_usdt);
    }

    public function test_non_super_admin_cannot_prepare_and_approve_same_period(): void
    {
        [$manager, , $investment, $period] = $this->fixture();
        $financeRole = AdminRole::where('code', 'FINANCE_ADMIN')->firstOrFail();
        $manager->roles()->attach($financeRole->getKey());
        $this->verifiedTrade($investment, $period, '1000', 'MAKER-CHECKER');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.profit_periods.prepare', [$investment, $period]))
            ->assertRedirect();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.profit_periods.approve', [$investment, $period]))
            ->assertSessionHasErrors('profit_period');

        $this->assertNull($period->fresh()->approved_at);
    }

    public function test_verified_original_trade_is_not_edited_when_reversed(): void
    {
        [$manager, $finance, $investment] = $this->fixture();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.trades.store', $investment), $this->tradePayload('BUY', 'ETH', '0'))
            ->assertRedirect();
        $trade = InvestmentTrade::firstOrFail();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.evidence.trade.store', [$investment, $trade]), [
                'document' => UploadedFile::fake()->createWithContent('eth.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
            ])->assertRedirect();
        $evidence = InvestmentEvidence::firstOrFail();
        $this->actingAs($finance, 'admin')->post(route('admin.investments.evidence.verify', [$investment, $evidence]));
        $this->actingAs($finance, 'admin')->post(route('admin.investments.trades.verify', [$investment, $trade]));

        $allocation = InvestmentAssetAllocation::where('source_trade_id', $trade->getKey())->firstOrFail();
        $originalQty = $trade->fresh()->quantity;

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.adjustments.store', $investment), [
                'target_type' => 'TRADE',
                'target_id' => $trade->getKey(),
                'adjustment_type' => 'REVERSAL',
                'reason' => 'Incorrect verified trade entry; reverse immutably.',
            ])->assertRedirect();

        $adjustment = InvestmentFinancialAdjustment::firstOrFail();
        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.adjustments.verify', [$investment, $adjustment]))
            ->assertRedirect();

        $this->assertSame(InvestmentTrade::STATUS_VERIFIED, $trade->fresh()->status);
        $this->assertSame($originalQty, $trade->fresh()->quantity);
        $this->assertSame('0.000000000000000000', $allocation->fresh()->remaining_units);
        $this->assertSame(InvestmentFinancialAdjustment::STATUS_VERIFIED, $adjustment->fresh()->status);
    }

    public function test_finance_approved_profit_payout_requires_txid_verification_before_settled(): void
    {
        [$manager, $finance, $investment, $period] = $this->fixture();
        $this->verifiedTrade($investment, $period, '1000', 'PAYOUT');

        $this->actingAs($manager, 'admin')->post(route('admin.investments.profit_periods.prepare', [$investment, $period]));
        $this->actingAs($finance, 'admin')->post(route('admin.investments.profit_periods.approve', [$investment, $period]));
        $period->refresh();
        $this->assertSame(InvestmentProfitPeriod::STATUS_APPROVED, $period->status);

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.profit_payouts.store', [$investment, $period]), [
                'txid' => str_repeat('b', 64),
                'occurred_at' => now()->toDateTimeString(),
            ])->assertRedirect();

        $payout = InvestmentTransaction::where('type', InvestmentTransaction::TYPE_PROFIT_PAYOUT)->firstOrFail();
        $this->assertSame(InvestmentTransaction::STATUS_PENDING, $payout->status);
        $this->assertSame(InvestmentProfitPeriod::STATUS_PAYMENT_RECORDED, $period->fresh()->status);

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.profit_payouts.verify', [$investment, $period, $payout]))
            ->assertRedirect();

        $this->assertSame(InvestmentTransaction::STATUS_VERIFIED, $payout->fresh()->status);
        $this->assertSame(InvestmentProfitPeriod::STATUS_SETTLED, $period->fresh()->status);
        $this->assertNotNull($period->fresh()->paid_at);
    }

    private function fixture(string $lossCarry = '0.00000000'): array
    {
        $manager = $this->adminWithRole('INVESTMENT_ADMIN', 'manager');
        $finance = $this->adminWithRole('FINANCE_ADMIN', 'finance');
        $investment = $this->activeInvestment($manager, $lossCarry);
        $period = $this->period($investment, 1, now()->subDays(30), now()->subDay());
        return [$manager, $finance, $investment, $period];
    }

    private function twoPeriodFixture(): array
    {
        $manager = $this->adminWithRole('INVESTMENT_ADMIN', 'manager2');
        $finance = $this->adminWithRole('FINANCE_ADMIN', 'finance2');
        $investment = $this->activeInvestment($manager);
        $period1 = $this->period($investment, 1, now()->subDays(60), now()->subDays(31));
        $period2 = $this->period($investment, 2, now()->subDays(30), now()->subDay());
        return [$manager, $finance, $investment, $period1, $period2];
    }

    private function activeInvestment(AdminUser $admin, string $lossCarry = '0.00000000'): Investment
    {
        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);
        $product = InvestmentProduct::where('code', InvestmentProduct::CODE_CRYPTO)->firstOrFail();

        return Investment::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_code' => 'TXI-P3-'.Str::upper(Str::random(8)),
            'user_id' => $user->getKey(),
            'investment_product_id' => $product->getKey(),
            'status' => Investment::STATUS_ACTIVE,
            'profit_model' => Investment::PROFIT_MODEL_PERFORMANCE_SHARE,
            'deposit_amount' => '10000.00000000',
            'deposit_currency' => 'USDT',
            'deposit_network' => 'TRC20',
            'investment_period_years' => 1,
            'profit_review_interval_months' => 2,
            'customer_profit_share_bps' => 8000,
            'company_profit_share_bps' => 2000,
            'early_exit_return_bps' => 8000,
            'fixed_return_guaranteed' => false,
            'settlement_mode' => Investment::SETTLEMENT_ON_MATURITY,
            'principal_settlement_status' => Investment::PRINCIPAL_NOT_DUE,
            'payout_wallet_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            'payout_wallet_network' => 'TRC20',
            'payout_wallet_verified_at' => now()->subMonth(),
            'payout_wallet_verified_by_admin_id' => $admin->getKey(),
            'high_water_mark_usdt' => '10000.00000000',
            'loss_carryforward_usdt' => $lossCarry,
            'start_date' => now()->subMonths(4)->toDateString(),
            'maturity_date' => now()->addMonths(8)->toDateString(),
            'principal_settlement_date' => now()->addMonths(8)->toDateString(),
            'activated_at' => now()->subMonths(4),
            'activated_by_admin_id' => $admin->getKey(),
            'created_by_admin_id' => $admin->getKey(),
            'updated_by_admin_id' => $admin->getKey(),
        ]);
    }

    private function period(Investment $investment, int $sequence, $start, $end): InvestmentProfitPeriod
    {
        return InvestmentProfitPeriod::query()->create([
            'investment_id' => $investment->getKey(),
            'sequence' => $sequence,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'status' => InvestmentProfitPeriod::STATUS_UPCOMING,
        ]);
    }

    private function verifiedTrade(
        Investment $investment,
        InvestmentProfitPeriod $period,
        string $pnl,
        string $suffix
    ): InvestmentTrade {
        $finance = AdminUser::whereHas('roles', fn ($q) => $q->where('code', 'FINANCE_ADMIN'))->firstOrFail();
        $trade = InvestmentTrade::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'action' => InvestmentTrade::ACTION_SELL,
            'instrument_type' => InvestmentTrade::INSTRUMENT_SPOT,
            'asset_name' => 'Bitcoin',
            'asset_symbol' => 'BTC',
            'exchange_broker' => 'TEST EXCHANGE',
            'quantity' => '0.010000000000000000',
            'executed_price_usdt' => '100000.00000000',
            'gross_value_usdt' => '1000.00000000',
            'fee_usdt' => '0.00000000',
            'realized_pnl_usdt' => $pnl,
            'executed_at' => $period->period_end->copy()->subDay()->startOfDay(),
            'status' => InvestmentTrade::STATUS_VERIFIED,
            'recorded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => now(),
        ]);

        $path = 'tests/'.$trade->uuid.'.png';
        Storage::disk('private')->put($path, 'verified-'.$suffix);
        InvestmentEvidence::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'investment_trade_id' => $trade->getKey(),
            'evidence_type' => InvestmentEvidence::TYPE_TRADE_SCREENSHOT,
            'document_disk' => 'private',
            'document_path' => $path,
            'document_mime_type' => 'image/png',
            'document_size_bytes' => strlen('verified-'.$suffix),
            'document_sha256' => hash('sha256', 'verified-'.$suffix),
            'status' => InvestmentEvidence::STATUS_VERIFIED,
            'uploaded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => now(),
        ]);

        return $trade;
    }

    private function verifiedCost(
        Investment $investment,
        InvestmentProfitPeriod $period,
        string $amount
    ): InvestmentApprovedCost {
        $finance = AdminUser::whereHas('roles', fn ($q) => $q->where('code', 'FINANCE_ADMIN'))->firstOrFail();
        return InvestmentApprovedCost::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'cost_type' => InvestmentApprovedCost::TYPE_BROKER_COMMISSION,
            'amount_usdt' => $amount,
            'occurred_at' => $period->period_end->copy()->subDay()->startOfDay(),
            'source_reference' => 'BROKER-COST-001',
            'status' => InvestmentApprovedCost::STATUS_VERIFIED,
            'recorded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => now(),
        ]);
    }

    private function tradePayload(string $action, string $symbol, string $pnl): array
    {
        return [
            'action' => $action,
            'asset_name' => $symbol.' Token',
            'asset_symbol' => $symbol,
            'network' => 'Ethereum',
            'contract_address' => '0x'.str_repeat('1', 40),
            'exchange_broker' => 'TEST EXCHANGE',
            'quantity' => '100.000000000000000000',
            'executed_price_usdt' => '1.00000000',
            'gross_value_usdt' => '100.00000000',
            'fee_usdt' => '0.00000000',
            'realized_pnl_usdt' => $pnl,
            'executed_at' => now()->subDays(2)->toDateTimeString(),
        ];
    }

    private function adminWithRole(string $roleCode, string $prefix): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $roleCode.' Phase 3',
            'email' => $prefix.'-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Investment#Phase3Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
        $role = AdminRole::where('code', $roleCode)->firstOrFail();
        $admin->roles()->attach($role->getKey());
        return $admin;
    }
}
