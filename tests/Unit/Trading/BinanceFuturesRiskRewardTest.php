<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\BinanceFuturesRiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use DomainException;
use Tests\TestCase;

class BinanceFuturesRiskRewardTest extends TestCase
{
    public function test_risk_based_mode_keeps_stop_risk_fixed_while_leverage_changes_margin(): void
    {
        $calculator = app(BinanceFuturesRiskRewardCalculatorService::class);
        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];

        $base = [
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
        ];

        $fiveX = $calculator->calculate($base + ['leverage' => 5], $market);
        $twentyX = $calculator->calculate($base + ['leverage' => 20], $market);

        $this->assertEqualsWithDelta(0.10, $fiveX['futures']['position_quantity'], 0.00000001);
        $this->assertEqualsWithDelta(0.10, $twentyX['futures']['position_quantity'], 0.00000001);
        $this->assertEqualsWithDelta(100.00, $fiveX['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(100.00, $twentyX['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(10000.00, $fiveX['futures']['position_notional'], 0.01);
        $this->assertEqualsWithDelta(2000.00, $fiveX['futures']['required_initial_margin'], 0.01);
        $this->assertEqualsWithDelta(500.00, $twentyX['futures']['required_initial_margin'], 0.01);
        $this->assertSame('RISK_BASED', $twentyX['futures']['calculation_mode']);
        $this->assertSame(20, $twentyX['futures']['leverage']);
    }

    public function test_margin_based_mode_uses_margin_times_leverage_for_notional_and_derives_risk(): void
    {
        $calculator = app(BinanceFuturesRiskRewardCalculatorService::class);
        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];

        $result = $calculator->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'MARGIN_BASED',
            'margin_amount' => 100,
            'leverage' => 20,
        ], $market);

        $this->assertEqualsWithDelta(2000.00, $result['futures']['position_notional'], 0.01);
        $this->assertEqualsWithDelta(0.02, $result['futures']['position_quantity'], 0.00000001);
        $this->assertEqualsWithDelta(100.00, $result['futures']['required_initial_margin'], 0.01);
        $this->assertEqualsWithDelta(20.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(40.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(1.00, $result['futures']['stop_loss_percent'], 0.000001);
        $this->assertEqualsWithDelta(2.00, $result['futures']['take_profit_percent'], 0.000001);
        $this->assertEqualsWithDelta(20.00, $result['futures']['estimated_sl_roe_percent'], 0.01);
        $this->assertEqualsWithDelta(40.00, $result['futures']['estimated_tp_roe_percent'], 0.01);
        $this->assertSame('USDT', $result['futures']['settlement_asset']);
    }

    public function test_leverage_outside_planning_range_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('between 1x and 125x');

        app(BinanceFuturesRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'take_profit' => 102000,
            'calculation_mode' => 'RISK_BASED',
            'risk_amount' => 100,
            'leverage' => 126,
        ], app(TradingInstrumentCatalog::class)->markets()['binance_futures']);
    }
}
