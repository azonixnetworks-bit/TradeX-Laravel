<?php

namespace Tests\Feature\Admin\Investments;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentAssetAllocation;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentProduct;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentSettlement;
use App\Models\InvestmentSettlementAsset;
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

class AdminInvestmentPhase4SettlementTest extends TestCase
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

    public function test_phase4_schema_and_maker_checker_permissions_are_present(): void
    {
        $this->assertTrue(Schema::hasTable('investment_settlements'));
        $this->assertTrue(Schema::hasTable('investment_settlement_assets'));
        $this->assertTrue(Schema::hasColumns('investment_transactions', [
            'investment_settlement_id',
            'investment_settlement_asset_id',
        ]));

        $investmentRole = AdminRole::where('code', 'INVESTMENT_ADMIN')->firstOrFail();
        $financeRole = AdminRole::where('code', 'FINANCE_ADMIN')->firstOrFail();

        $this->assertTrue($investmentRole->hasPermission('investments.settlements.prepare'));
        $this->assertTrue($investmentRole->hasPermission('investments.settlements.wallets.record'));
        $this->assertFalse($investmentRole->hasPermission('investments.principal_settlement.approve'));
        $this->assertTrue($financeRole->hasPermission('investments.principal_settlement.approve'));
        $this->assertTrue($financeRole->hasPermission('investments.settlements.wallets.verify'));
        $this->assertTrue($financeRole->hasPermission('investments.settlements.payments.verify'));
    }

    public function test_early_termination_freezes_activity_and_prepares_eighty_percent_in_kind_units(): void
    {
        [$manager, $finance, $investment, $period] = $this->activeFixture();
        $trade = $this->verifiedBuy($investment, $period, 'BTC', '1.000000000000000000', '5000.00000000');

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.early.initiate', $investment), [
                'reason' => 'Customer requested contractual early termination after risk review.',
            ])->assertRedirect();

        $investment->refresh();
        $this->assertSame(Investment::STATUS_EARLY_TERMINATION_REVIEW, $investment->status);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.topups.store', $investment), [
                'amount' => '100',
                'txid' => str_repeat('d', 64),
                'receiving_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                'occurred_at' => now()->toDateTimeString(),
            ])->assertSessionHasErrors('top_up');

        $payload = $this->tradePayload('BUY', 'ETH', '0');
        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.trades.store', $investment), $payload)
            ->assertSessionHasErrors('trade');

        $settlement = InvestmentSettlement::firstOrFail();
        $this->verifiedTerminalPnlEvidence(
            $manager,
            $finance,
            $investment,
            $settlement,
            'EARLY-ZERO'
        );

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.prepare', [$investment, $settlement]))
            ->assertRedirect();

        $settlement->refresh();
        $this->assertSame(InvestmentSettlement::STATUS_PREPARED, $settlement->status);
        $this->assertSame(8000, $settlement->return_bps);
        $this->assertSame(64, strlen((string) $settlement->calculation_source_sha256));

        $btc = InvestmentSettlementAsset::where('investment_settlement_id', $settlement->getKey())
            ->where('asset_symbol', 'BTC')
            ->firstOrFail();

        $this->assertSame('1.000000000000000000', $btc->gross_attributable_units);
        $this->assertSame('0.800000000000000000', $btc->customer_return_units);
        $this->assertSame('0.200000000000000000', $btc->early_exit_adjustment_units);
        $this->assertSame($trade->contract_address, $btc->contract_address);
        $this->assertSame(Investment::PRINCIPAL_PAYMENT_PREPARED, $investment->fresh()->principal_settlement_status);
    }

    public function test_maturity_settlement_returns_one_hundred_percent_of_attributable_units(): void
    {
        [$manager, , $investment, $period] = $this->maturedFixture();
        $this->verifiedBuy($investment, $period, 'BTC', '1.250000000000000000', '5000.00000000', true);

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.maturity.prepare', $investment))
            ->assertRedirect();

        $settlement = InvestmentSettlement::firstOrFail();
        $btc = InvestmentSettlementAsset::where('investment_settlement_id', $settlement->getKey())
            ->where('asset_symbol', 'BTC')
            ->firstOrFail();

        $this->assertSame(InvestmentSettlement::TYPE_MATURITY, $settlement->type);
        $this->assertSame(10000, $settlement->return_bps);
        $this->assertSame('1.250000000000000000', $btc->gross_attributable_units);
        $this->assertSame('1.250000000000000000', $btc->customer_return_units);
        $this->assertSame('0.000000000000000000', $btc->early_exit_adjustment_units);
        $this->assertSame(Investment::STATUS_MATURED, $investment->fresh()->status);
    }

    public function test_maker_checker_wallet_payment_fee_and_explicit_close_flow(): void
    {
        [$manager, $finance, $investment, $period] = $this->activeFixture();
        $this->verifiedBuy($investment, $period, 'BTC', '1.000000000000000000', '5000.00000000');

        $this->actingAs($manager, 'admin')->post(
            route('admin.investments.settlements.early.initiate', $investment),
            ['reason' => 'Customer requested contractual early termination for settlement flow test.']
        );

        $settlement = InvestmentSettlement::firstOrFail();
        $this->verifiedTerminalPnlEvidence(
            $manager,
            $finance,
            $investment,
            $settlement,
            'FLOW'
        );
        $this->actingAs($manager, 'admin')->post(
            route('admin.investments.settlements.prepare', [$investment, $settlement])
        );
        $settlement->refresh();

        $financeRole = AdminRole::where('code', 'FINANCE_ADMIN')->firstOrFail();
        $manager->roles()->attach($financeRole->getKey());

        foreach ($settlement->assets()->orderBy('id')->get() as $asset) {
            $payoutNetwork = $asset->asset_network ?: 'TRC20';
            $destinationWallet = strcasecmp($payoutNetwork, 'TRC20') === 0
                ? 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'
                : 'wallet-'.$asset->id.'-customer-verified-address';

            $this->actingAs($manager, 'admin')
                ->post(route('admin.investments.settlements.wallet.record', [$investment, $settlement, $asset]), [
                    'payout_network' => $payoutNetwork,
                    'destination_wallet' => $destinationWallet,
                    'wallet_reference' => 'CUSTOMER-WALLET-'.$asset->id,
                ])->assertRedirect();

            $this->actingAs($finance, 'admin')
                ->post(route('admin.investments.settlements.wallet.verify', [$investment, $settlement, $asset]))
                ->assertRedirect();
        }

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.approve', [$investment, $settlement]))
            ->assertSessionHasErrors('settlement');

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.settlements.approve', [$investment, $settlement]))
            ->assertRedirect();

        $settlement->refresh();
        $this->assertSame(InvestmentSettlement::STATUS_APPROVED, $settlement->status);

        $counter = 0;
        foreach ($settlement->assets()->orderBy('id')->get() as $asset) {
            $counter++;
            $fee = $asset->asset_symbol === 'BTC' ? '0.001000000000000000' : '0';
            $deduct = $asset->asset_symbol === 'BTC' ? 1 : 0;

            $this->actingAs($finance, 'admin')
                ->post(route('admin.investments.settlements.asset_payout.record', [$investment, $settlement, $asset]), [
                    'network_fee_asset_symbol' => $asset->asset_symbol,
                    'network_fee_units' => $fee,
                    'deduct_fee_from_customer' => $deduct,
                    'txid' => 'phase4-asset-tx-'.$counter.'-'.Str::lower(Str::random(24)),
                    'occurred_at' => now()->toDateTimeString(),
                ])->assertRedirect();

            $transaction = InvestmentTransaction::where('investment_settlement_asset_id', $asset->getKey())
                ->latest('id')
                ->firstOrFail();

            $this->assertSame(InvestmentTransaction::STATUS_PENDING, $transaction->status);

            $this->actingAs($finance, 'admin')
                ->post(route('admin.investments.settlements.asset_payout.verify', [$investment, $settlement, $asset, $transaction]))
                ->assertRedirect();
        }

        $settlement->refresh();
        $this->assertSame(InvestmentSettlement::STATUS_SETTLED, $settlement->status);
        $this->assertSame(Investment::STATUS_EARLY_TERMINATED, $investment->fresh()->status);

        $btc = $settlement->assets()->where('asset_symbol', 'BTC')->firstOrFail();
        $this->assertSame('0.001000000000000000', $btc->network_fee_units);
        $this->assertTrue($btc->network_fee_deducted_from_customer);
        $this->assertSame('0.799000000000000000', $btc->net_transfer_units);

        $this->actingAs($finance, 'admin')
            ->post(route('admin.investments.settlements.close', [$investment, $settlement]))
            ->assertRedirect();

        $investment->refresh();
        $this->assertSame(Investment::STATUS_CLOSED, $investment->status);
        $this->assertSame(Investment::PRINCIPAL_SETTLED, $investment->principal_settlement_status);
        $this->assertNotNull($investment->closed_at);
    }

    public function test_early_termination_terminal_realized_profit_uses_loss_recovery_and_eighty_twenty_split(): void
    {
        [$manager, $finance, $investment, $period] = $this->activeFixture();
        $this->verifiedSellProfit($investment, $period, '1000.00000000');

        $this->actingAs($manager, 'admin')->post(
            route('admin.investments.settlements.early.initiate', $investment),
            ['reason' => 'Customer requested contractual early termination with accrued realized profit.']
        );

        $settlement = InvestmentSettlement::firstOrFail();
        $this->verifiedTerminalPnlEvidence(
            $manager,
            $finance,
            $investment,
            $settlement,
            'TERMINAL-PROFIT'
        );
        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.prepare', [$investment, $settlement]))
            ->assertRedirect();

        $settlement->refresh();
        $this->assertSame('1000.00000000', $settlement->eligible_net_profit_usdt);
        $this->assertSame('800.00000000', $settlement->customer_profit_share_usdt);
        $this->assertSame('200.00000000', $settlement->company_profit_share_usdt);
        $this->assertSame('0.00000000', $settlement->rounding_adjustment_usdt);
    }

    public function test_early_termination_preparation_requires_finance_verified_pnl_evidence(): void
    {
        [$manager, , $investment, $period] = $this->activeFixture();
        $this->verifiedSellProfit($investment, $period, '500.00000000');
        $this->verifiedPnlEvidence(
            $investment,
            $period,
            'GENERIC-DOES-NOT-SATISFY-CUTOFF'
        );

        $this->actingAs($manager, 'admin')->post(
            route('admin.investments.settlements.early.initiate', $investment),
            ['reason' => 'Customer requested contractual early termination without final PnL proof.']
        );

        $settlement = InvestmentSettlement::firstOrFail();
        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.settlements.prepare', [$investment, $settlement]))
            ->assertSessionHasErrors('settlement');

        $this->assertSame(InvestmentSettlement::STATUS_DRAFT, $settlement->fresh()->status);
    }

    public function test_trade_cannot_be_backdated_or_recorded_after_maturity_even_if_status_is_still_active(): void
    {
        [$manager, , $investment] = $this->maturedFixture();
        $investment->forceFill(['status' => Investment::STATUS_ACTIVE])->save();

        $payload = $this->tradePayload('BUY', 'ETH', '0');
        $payload['executed_at'] = now()->toDateTimeString();

        $this->actingAs($manager, 'admin')
            ->post(route('admin.investments.trades.store', $investment), $payload)
            ->assertSessionHasErrors('trade');

        $this->assertDatabaseCount('investment_trades', 0);
    }

    public function test_early_termination_preflight_blocks_unfinished_prior_period_inflight_cutoff_period_and_pending_topup(): void
    {
        $manager = $this->adminWithRole(
            'INVESTMENT_ADMIN',
            'phase4-preflight-manager'
        );
        $finance = $this->adminWithRole(
            'FINANCE_ADMIN',
            'phase4-preflight-finance'
        );

        $investment = $this->investment(
            $manager,
            now()->subMonths(4),
            now()->addMonths(8)
        );

        $prior = $this->period(
            $investment,
            1,
            now()->subMonths(4),
            now()->subDay()
        );

        $current = $this->period(
            $investment,
            2,
            now()->startOfDay(),
            now()->addMonths(2)
        );

        $this->verifiedDeposit(
            $investment,
            $manager,
            now()->subMonths(4)->addDay()
        );

        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.early.initiate',
                    $investment
                ),
                [
                    'reason' =>
                        'Preflight must block an unfinished earlier review period.',
                ]
            )
            ->assertSessionHasErrors('settlement');

        $this->assertSame(
            Investment::STATUS_ACTIVE,
            $investment->fresh()->status
        );
        $this->assertDatabaseCount(
            'investment_settlements',
            0
        );

        $prior->forceFill([
            'status' =>
                InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
            'distribution_outcome' =>
                InvestmentProfitPeriod::OUTCOME_NO_DISTRIBUTION,
            'approved_by_admin_id' =>
                $finance->getKey(),
            'approved_at' => now()->subHour(),
        ])->save();

        $current->forceFill([
            'status' =>
                InvestmentProfitPeriod::STATUS_APPROVAL_PENDING,
            'prepared_by_admin_id' =>
                $manager->getKey(),
            'prepared_at' => now()->subMinutes(20),
        ])->save();

        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.early.initiate',
                    $investment
                ),
                [
                    'reason' =>
                        'Preflight must block an in-flight cutoff-period distribution workflow.',
                ]
            )
            ->assertSessionHasErrors('settlement');

        $current->forceFill([
            'status' =>
                InvestmentProfitPeriod::STATUS_UPCOMING,
            'prepared_by_admin_id' => null,
            'prepared_at' => null,
        ])->save();

        $pendingTopUp =
            InvestmentTransaction::query()->create([
                'uuid' => Str::uuid()->toString(),
                'investment_id' =>
                    $investment->getKey(),
                'direction' =>
                    InvestmentTransaction::DIRECTION_IN,
                'type' =>
                    InvestmentTransaction::TYPE_TOP_UP,
                'asset_symbol' => 'USDT',
                'amount' =>
                    '100.000000000000000000',
                'usdt_equivalent' =>
                    '100.00000000',
                'network' => 'TRC20',
                'to_address' =>
                    'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                'txid' => str_repeat('b', 64),
                'status' =>
                    InvestmentTransaction::STATUS_PENDING,
                'occurred_at' => now()->subMinute(),
                'recorded_by_admin_id' =>
                    $manager->getKey(),
            ]);

        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.early.initiate',
                    $investment
                ),
                [
                    'reason' =>
                        'Preflight must block a pending pre-cutoff top-up.',
                ]
            )
            ->assertSessionHasErrors('settlement');

        $pendingTopUp->forceFill([
            'status' =>
                InvestmentTransaction::STATUS_REJECTED,
            'verified_by_admin_id' =>
                $finance->getKey(),
            'verified_at' => now(),
        ])->save();

        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.early.initiate',
                    $investment
                ),
                [
                    'reason' =>
                        'All prior review and top-up preflight gates are now clear.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            Investment::STATUS_EARLY_TERMINATION_REVIEW,
            $investment->fresh()->status
        );
        $this->assertDatabaseCount(
            'investment_settlements',
            1
        );
    }
    public function test_post_approval_payouts_cannot_be_backdated_and_terminal_profit_cannot_be_recorded_twice(): void
    {
        [$manager, $finance, $investment, $period] = $this->activeFixture();
        $this->verifiedSellProfit(
            $investment,
            $period,
            '1000.00000000'
        );

        $this->actingAs($manager, 'admin')->post(
            route(
                'admin.investments.settlements.early.initiate',
                $investment
            ),
            [
                'reason' =>
                    'Customer requested early termination for payout chronology test.',
            ]
        );

        $settlement = InvestmentSettlement::firstOrFail();

        $this->verifiedTerminalPnlEvidence(
            $manager,
            $finance,
            $investment,
            $settlement,
            'PAYOUT-GUARD'
        );

        $this->actingAs($manager, 'admin')->post(
            route(
                'admin.investments.settlements.prepare',
                [$investment, $settlement]
            )
        );

        $settlement->refresh();

        foreach ($settlement->assets()->orderBy('id')->get() as $asset) {
            $network = $asset->asset_network ?: 'TRC20';
            $wallet = strcasecmp($network, 'TRC20') === 0
                ? 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE'
                : 'verified-wallet-'.$asset->id;

            $this->actingAs($manager, 'admin')->post(
                route(
                    'admin.investments.settlements.wallet.record',
                    [$investment, $settlement, $asset]
                ),
                [
                    'payout_network' => $network,
                    'destination_wallet' => $wallet,
                    'wallet_reference' =>
                        'PAYOUT-GUARD-'.$asset->id,
                ]
            );

            $this->actingAs($finance, 'admin')->post(
                route(
                    'admin.investments.settlements.wallet.verify',
                    [$investment, $settlement, $asset]
                )
            );
        }

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.approve',
                    [$investment, $settlement]
                )
            )
            ->assertRedirect();

        $settlement->refresh();
        $backdated = $settlement->approved_at
            ->copy()
            ->subMinute()
            ->toDateTimeString();

        $firstAsset = $settlement->assets()
            ->orderBy('id')
            ->firstOrFail();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.asset_payout.record',
                    [
                        $investment,
                        $settlement,
                        $firstAsset,
                    ]
                ),
                [
                    'network_fee_asset_symbol' =>
                        $firstAsset->asset_symbol,
                    'network_fee_units' => '0',
                    'txid' =>
                        'backdated-asset-'
                        .Str::lower(Str::random(24)),
                    'occurred_at' => $backdated,
                ]
            )
            ->assertSessionHasErrors('payment');

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_profit.record',
                    [$investment, $settlement]
                ),
                [
                    'txid' =>
                        str_repeat('e', 64),
                    'occurred_at' => $backdated,
                ]
            )
            ->assertSessionHasErrors('payment');

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.asset_payout.record',
                    [
                        $investment,
                        $settlement,
                        $firstAsset,
                    ]
                ),
                [
                    'network_fee_asset_symbol' =>
                        $firstAsset->asset_symbol,
                    'network_fee_units' => '0',
                    'txid' =>
                        'asset-reject-'
                        .Str::lower(Str::random(32)),
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $firstAssetTransaction =
            InvestmentTransaction::query()
                ->where(
                    'investment_settlement_asset_id',
                    $firstAsset->getKey()
                )
                ->latest('id')
                ->firstOrFail();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.asset_payout.reject',
                    [
                        $investment,
                        $settlement,
                        $firstAsset,
                        $firstAssetTransaction,
                    ]
                ),
                [
                    'reason' =>
                        'Finance rejected the pending transfer because the recorded transaction reference was incorrect.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            InvestmentTransaction::STATUS_REJECTED,
            $firstAssetTransaction->fresh()->status
        );
        $this->assertSame(
            InvestmentSettlementAsset::STATUS_READY,
            $firstAsset->fresh()->status
        );

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.asset_payout.record',
                    [
                        $investment,
                        $settlement,
                        $firstAsset,
                    ]
                ),
                [
                    'network_fee_asset_symbol' =>
                        $firstAsset->asset_symbol,
                    'network_fee_units' => '0',
                    'txid' =>
                        'asset-retry-'
                        .Str::lower(Str::random(32)),
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_profit.record',
                    [$investment, $settlement]
                ),
                [
                    'txid' =>
                        str_repeat('f', 64),
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_profit.record',
                    [$investment, $settlement]
                ),
                [
                    'txid' =>
                        str_repeat('a', 64),
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertSessionHasErrors('payment');

        $this->assertSame(
            1,
            InvestmentTransaction::query()
                ->where(
                    'investment_settlement_id',
                    $settlement->getKey()
                )
                ->where(
                    'type',
                    InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                )
                ->count()
        );

        $terminalProfitTransaction =
            InvestmentTransaction::query()
                ->where(
                    'investment_settlement_id',
                    $settlement->getKey()
                )
                ->where(
                    'type',
                    InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                )
                ->latest('id')
                ->firstOrFail();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_profit.reject',
                    [
                        $investment,
                        $settlement,
                        $terminalProfitTransaction,
                    ]
                ),
                [
                    'reason' =>
                        'Finance rejected the pending terminal profit payout because the transaction reference requires correction.',
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            InvestmentTransaction::STATUS_REJECTED,
            $terminalProfitTransaction->fresh()->status
        );

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_profit.record',
                    [$investment, $settlement]
                ),
                [
                    'txid' =>
                        str_repeat('c', 64),
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $this->assertSame(
            2,
            InvestmentTransaction::query()
                ->where(
                    'investment_settlement_id',
                    $settlement->getKey()
                )
                ->where(
                    'type',
                    InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                )
                ->count()
        );

        $this->assertSame(
            1,
            InvestmentTransaction::query()
                ->where(
                    'investment_settlement_id',
                    $settlement->getKey()
                )
                ->where(
                    'type',
                    InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                )
                ->where(
                    'status',
                    InvestmentTransaction::STATUS_PENDING
                )
                ->count()
        );
    }
    public function test_spot_trade_requires_network_for_exact_terminal_asset_identity(): void
    {
        [$manager, , $investment] =
            $this->activeFixture();

        $payload = $this->tradePayload(
            'BUY',
            'ETH',
            '0'
        );
        unset($payload['network']);

        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.trades.store',
                    $investment
                ),
                $payload
            )
            ->assertSessionHasErrors('network');
    }
    private function activeFixture(): array
    {
        $manager = $this->adminWithRole('INVESTMENT_ADMIN', 'phase4-manager');
        $finance = $this->adminWithRole('FINANCE_ADMIN', 'phase4-finance');
        $investment = $this->investment($manager, now()->subMonth(), now()->addMonths(11));
        $period = $this->period($investment, 1, now()->subMonth(), now()->addMonth());
        $this->verifiedDeposit($investment, $manager, now()->subMonth()->addDay());

        return [$manager, $finance, $investment, $period];
    }

    private function maturedFixture(): array
    {
        $manager = $this->adminWithRole('INVESTMENT_ADMIN', 'phase4-maturity-manager');
        $finance = $this->adminWithRole('FINANCE_ADMIN', 'phase4-maturity-finance');
        $start = now()->subYear()->subDay();
        $maturity = now()->subDay();
        $investment = $this->investment($manager, $start, $maturity);
        $period = $this->period($investment, 1, $start, $maturity);
        $period->forceFill([
            'status' => InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
            'distribution_outcome' => InvestmentProfitPeriod::OUTCOME_NO_DISTRIBUTION,
            'approved_by_admin_id' => $finance->getKey(),
            'approved_at' => now()->subDay(),
        ])->save();
        $this->verifiedDeposit($investment, $manager, $start->copy()->addDay());

        return [$manager, $finance, $investment, $period];
    }

    private function investment(AdminUser $admin, $start, $maturity): Investment
    {
        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);
        $product = InvestmentProduct::where('code', InvestmentProduct::CODE_CRYPTO)->firstOrFail();

        return Investment::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_code' => 'TXI-P4-'.Str::upper(Str::random(8)),
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
            'start_date' => $start->toDateString(),
            'maturity_date' => $maturity->toDateString(),
            'principal_settlement_date' => $maturity->toDateString(),
            'principal_settlement_status' => Investment::PRINCIPAL_NOT_DUE,
            'payout_wallet_address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            'payout_wallet_network' => 'TRC20',
            'payout_wallet_verified_at' => now()->subMonth(),
            'payout_wallet_verified_by_admin_id' => $admin->getKey(),
            'high_water_mark_usdt' => '10000.00000000',
            'loss_carryforward_usdt' => '0.00000000',
            'activated_at' => $start,
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

    private function verifiedDeposit(Investment $investment, AdminUser $admin, $occurredAt): void
    {
        InvestmentTransaction::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'direction' => InvestmentTransaction::DIRECTION_IN,
            'type' => InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT,
            'asset_symbol' => 'USDT',
            'amount' => '10000.000000000000000000',
            'usdt_equivalent' => '10000.00000000',
            'network' => 'TRC20',
            'txid' => Str::lower(Str::random(64)),
            'status' => InvestmentTransaction::STATUS_VERIFIED,
            'occurred_at' => $occurredAt,
            'recorded_by_admin_id' => $admin->getKey(),
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => $occurredAt,
        ]);
    }

    private function verifiedBuy(
        Investment $investment,
        InvestmentProfitPeriod $period,
        string $symbol,
        string $quantity,
        string $grossValue,
        bool $beforeMaturity = false
    ): InvestmentTrade {
        $finance = AdminUser::whereHas('roles', fn ($q) => $q->where('code', 'FINANCE_ADMIN'))->firstOrFail();
        $executedAt = $beforeMaturity
            ? $investment->maturity_date->copy()->subDays(10)->startOfDay()
            : now()->subDays(3);

        $trade = InvestmentTrade::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'action' => InvestmentTrade::ACTION_BUY,
            'instrument_type' => InvestmentTrade::INSTRUMENT_SPOT,
            'asset_name' => 'Bitcoin',
            'asset_symbol' => $symbol,
            'network' => 'Bitcoin',
            'contract_address' => null,
            'exchange_broker' => 'TEST EXCHANGE',
            'quantity' => $quantity,
            'executed_price_usdt' => '4000.00000000',
            'gross_value_usdt' => $grossValue,
            'fee_usdt' => '0.00000000',
            'realized_pnl_usdt' => '0.00000000',
            'executed_at' => $executedAt,
            'status' => InvestmentTrade::STATUS_VERIFIED,
            'recorded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => $executedAt,
        ]);

        $this->verifiedTradeEvidence($investment, $period, $trade, 'BUY-'.$symbol);

        InvestmentAssetAllocation::query()->create([
            'investment_id' => $investment->getKey(),
            'source_trade_id' => $trade->getKey(),
            'asset_symbol' => $symbol,
            'network' => 'Bitcoin',
            'contract_address' => null,
            'original_units' => $quantity,
            'remaining_units' => $quantity,
            'allocated_cost_usdt' => $grossValue,
            'acquired_at' => $executedAt,
            'status' => InvestmentAssetAllocation::STATUS_ACTIVE,
        ]);

        return $trade;
    }

    private function verifiedSellProfit(
        Investment $investment,
        InvestmentProfitPeriod $period,
        string $pnl
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
            'network' => 'Bitcoin',
            'exchange_broker' => 'TEST EXCHANGE',
            'quantity' => '0.100000000000000000',
            'executed_price_usdt' => '10000.00000000',
            'gross_value_usdt' => '1000.00000000',
            'fee_usdt' => '0.00000000',
            'realized_pnl_usdt' => $pnl,
            'executed_at' => now()->subDays(2),
            'status' => InvestmentTrade::STATUS_VERIFIED,
            'recorded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => now()->subDays(2),
        ]);

        $this->verifiedTradeEvidence($investment, $period, $trade, 'SELL-PROFIT');

        return $trade;
    }

    private function verifiedTradeEvidence(
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTrade $trade,
        string $suffix
    ): void {
        $finance = AdminUser::whereHas('roles', fn ($q) => $q->where('code', 'FINANCE_ADMIN'))->firstOrFail();
        $bytes = 'phase4-trade-'.$suffix;
        $path = 'tests/phase4/'.$trade->uuid.'.png';
        Storage::disk('private')->put($path, $bytes);

        InvestmentEvidence::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'investment_trade_id' => $trade->getKey(),
            'evidence_type' => InvestmentEvidence::TYPE_TRADE_SCREENSHOT,
            'document_disk' => 'private',
            'document_path' => $path,
            'document_mime_type' => 'image/png',
            'document_size_bytes' => strlen($bytes),
            'document_sha256' => hash('sha256', $bytes),
            'status' => InvestmentEvidence::STATUS_VERIFIED,
            'uploaded_by_admin_id' => $finance->getKey(),
            'verified_by_admin_id' => $finance->getKey(),
            'verified_at' => now(),
        ]);
    }

    private function verifiedTerminalPnlEvidence(
        AdminUser $manager,
        AdminUser $finance,
        Investment $investment,
        InvestmentSettlement $settlement,
        string $suffix
    ): InvestmentEvidence {
        $this->actingAs($manager, 'admin')
            ->post(
                route(
                    'admin.investments.settlements.terminal_pnl_evidence.store',
                    [$investment, $settlement]
                ),
                [
                    'document' =>
                        UploadedFile::fake()->create(
                            'terminal-'.$suffix.'.pdf',
                            10,
                            'application/pdf'
                        ),
                    'account_reference' =>
                        'ACCOUNT-'.$suffix,
                    'source_reference' =>
                        'SOURCE-'.$suffix,
                ]
            )
            ->assertRedirect();

        $evidence = InvestmentEvidence::query()
            ->where(
                'investment_settlement_id',
                $settlement->getKey()
            )
            ->where(
                'evidence_type',
                InvestmentEvidence::TYPE_TERMINAL_PNL_STATEMENT
            )
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($finance, 'admin')
            ->post(
                route(
                    'admin.investments.evidence.verify',
                    [$investment, $evidence]
                )
            )
            ->assertRedirect();

        return $evidence->fresh();
    }
    private function verifiedPnlEvidence(
        Investment $investment,
        InvestmentProfitPeriod $period,
        string $suffix
    ): void {
        $finance = AdminUser::whereHas('roles', fn ($q) => $q->where('code', 'FINANCE_ADMIN'))->firstOrFail();
        $bytes = 'phase4-pnl-'.$suffix;
        $path = 'tests/phase4/pnl-'.$period->id.'-'.Str::lower(Str::random(8)).'.pdf';
        Storage::disk('private')->put($path, $bytes);

        InvestmentEvidence::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'evidence_type' => InvestmentEvidence::TYPE_PNL_STATEMENT,
            'period_start' => $period->period_start,
            'period_end' => $period->period_end,
            'document_disk' => 'private',
            'document_path' => $path,
            'document_mime_type' => 'application/pdf',
            'document_size_bytes' => strlen($bytes),
            'document_sha256' => hash('sha256', $bytes),
            'status' => InvestmentEvidence::STATUS_VERIFIED,
            'uploaded_by_admin_id' => $finance->getKey(),
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
            'contract_address' => '0x'.str_repeat('2', 40),
            'exchange_broker' => 'TEST EXCHANGE',
            'quantity' => '1.000000000000000000',
            'executed_price_usdt' => '100.00000000',
            'gross_value_usdt' => '100.00000000',
            'fee_usdt' => '0.00000000',
            'realized_pnl_usdt' => $pnl,
            'executed_at' => now()->subHour()->toDateTimeString(),
        ];
    }

    private function adminWithRole(string $roleCode, string $prefix): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => $roleCode.' Phase 4',
            'email' => $prefix.'-'.Str::lower(Str::random(8)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Investment#Phase4Password2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::where('code', $roleCode)->firstOrFail();
        $admin->roles()->attach($role->getKey());

        return $admin;
    }
}
