<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\BinanceFuturesRiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use Tests\TestCase;

class BinanceFuturesCostLiquidationTest extends TestCase
{
    public function test_risk_based_mode_sizes_to_all_in_stop_budget_when_costs_are_supplied(): void
    {
        $result = $this->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
            'leverage' => 10,
            'entry_fee_rate_percent' => 0.05,
            'exit_fee_rate_percent' => 0.05,
            'slippage_percent' => 0.10,
            'funding_rate_percent' => 0.01,
            'funding_intervals' => 2,
        ]);

        $this->assertEqualsWithDelta(0.08206811, $result['futures']['position_quantity'], 0.00000001);
        $this->assertLessThan(100.00, $result['actual_risk']);
        $this->assertEqualsWithDelta(100.00, $result['futures']['all_in_stop_risk'], 0.01);
        $this->assertEqualsWithDelta(145.8350, $result['futures']['net_target_profit'], 0.01);
        $this->assertEqualsWithDelta(4.1034, $result['futures']['costs']['entry_fee'], 0.01);
        $this->assertEqualsWithDelta(1.6414, $result['futures']['costs']['funding_estimate'], 0.01);
        $this->assertLessThanOrEqual(100.0001, $result['futures']['all_in_stop_risk']);
    }

    public function test_margin_based_mode_reports_fee_slippage_and_funding_adjusted_results(): void
    {
        $result = $this->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'MARGIN_BASED',
            'margin_amount' => 100,
            'leverage' => 20,
            'entry_fee_rate_percent' => 0.05,
            'exit_fee_rate_percent' => 0.05,
            'slippage_percent' => 0.10,
            'funding_rate_percent' => 0.01,
            'funding_intervals' => 2,
        ]);

        $this->assertEqualsWithDelta(20.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(24.37, $result['futures']['all_in_stop_risk'], 0.01);
        $this->assertEqualsWithDelta(35.54, $result['futures']['net_target_profit'], 0.01);
        $this->assertEqualsWithDelta(1.00, $result['futures']['costs']['entry_fee'], 0.01);
        $this->assertEqualsWithDelta(0.99, $result['futures']['costs']['stop_exit_fee'], 0.01);
        $this->assertEqualsWithDelta(1.98, $result['futures']['costs']['stop_slippage'], 0.01);
        $this->assertEqualsWithDelta(0.40, $result['futures']['costs']['funding_estimate'], 0.01);
    }

    public function test_isolated_liquidation_estimate_uses_live_tier_inputs_and_detects_safe_stop_buffer(): void
    {
        $result = $this->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
            'leverage' => 10,
            'margin_mode' => 'ISOLATED',
            'maintenance_margin_rate_percent' => 0.4,
            'maintenance_amount' => 0,
        ]);

        $liquidation = $result['futures']['liquidation'];

        $this->assertTrue($liquidation['available']);
        $this->assertEqualsWithDelta(90361.4458, $liquidation['estimated_price'], 0.01);
        $this->assertEqualsWithDelta(9.6386, $liquidation['distance_from_entry_percent'], 0.001);
        $this->assertEqualsWithDelta(8.6386, $liquidation['stop_buffer_percent'], 0.001);
        $this->assertTrue($liquidation['stop_before_liquidation']);
        $this->assertEqualsWithDelta(1000.00, $liquidation['wallet_balance_used'], 0.01);
    }

    public function test_high_leverage_warning_is_added_when_liquidation_precedes_stop(): void
    {
        $result = $this->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
            'leverage' => 125,
            'margin_mode' => 'ISOLATED',
            'maintenance_margin_rate_percent' => 0.4,
            'maintenance_amount' => 0,
        ]);

        $liquidation = $result['futures']['liquidation'];

        $this->assertTrue($liquidation['available']);
        $this->assertEqualsWithDelta(99598.3936, $liquidation['estimated_price'], 0.01);
        $this->assertFalse($liquidation['stop_before_liquidation']);
        $this->assertLessThan(0, $liquidation['stop_buffer_percent']);
        $this->assertStringContainsString(
            'before or at the planned Stop Loss',
            implode(' ', $result['warnings'])
        );
    }

    public function test_cross_margin_refuses_to_fake_single_trade_liquidation_price(): void
    {
        $result = $this->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
            'leverage' => 10,
            'margin_mode' => 'CROSS',
        ]);

        $this->assertFalse($result['futures']['liquidation']['available']);
        $this->assertNull($result['futures']['liquidation']['estimated_price']);
        $this->assertStringContainsString(
            'Cross-margin liquidation is not estimated',
            implode(' ', $result['warnings'])
        );
    }

    /** @param array<string, mixed> $input */
    private function calculate(array $input): array
    {
        return app(BinanceFuturesRiskRewardCalculatorService::class)->calculate(
            $input,
            app(TradingInstrumentCatalog::class)->markets()['binance_futures']
        );
    }
}
