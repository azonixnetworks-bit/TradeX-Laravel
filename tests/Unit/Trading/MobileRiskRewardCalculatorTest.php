<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\BinanceFuturesRiskRewardCalculatorService;
use App\Services\Trading\DividendCalculatorService;
use App\Services\Trading\RiskRewardCalculatorService;
use App\Services\Trading\StockRiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use DomainException;
use Tests\TestCase;

class MobileRiskRewardCalculatorTest extends TestCase
{
    public function test_forex_position_sizing_works_without_take_profit(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'side' => 'BUY',
            'entry' => 1.1000,
            'stop_loss' => 1.0950,
            'risk_amount' => 100,
        ]);

        $this->assertNotNull($result['recommended_lot']);
        $this->assertNull($result['take_profit']);
        $this->assertNull($result['potential_profit']);
        $this->assertNull($result['risk_reward_ratio']);
    }

    public function test_binance_position_sizing_works_without_take_profit(): void
    {
        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];
        $symbol = array_key_first($market['instruments']);

        $result = app(BinanceFuturesRiskRewardCalculatorService::class)->calculate([
            'symbol' => $symbol,
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'risk_amount' => 100,
            'calculation_mode' => 'RISK_BASED',
            'margin_mode' => 'ISOLATED',
            'leverage' => 10,
        ], $market);

        $this->assertNull($result['take_profit']);
        $this->assertNull($result['potential_profit']);
        $this->assertNull($result['risk_reward_ratio']);
        $this->assertNotNull($result['futures']['required_initial_margin']);
    }

    public function test_stock_calculator_converts_usd_risk_and_rounds_down_to_whole_shares(): void
    {
        $result = app(StockRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'CSE-MANUAL',
            'company_name' => 'Manual CSE Company',
            'region' => 'SRI_LANKA',
            'exchange' => 'CSE',
            'currency' => 'LKR',
            'side' => 'BUY',
            'entry' => 100,
            'stop_loss' => 95,
            'take_profit' => 110,
            'risk_amount' => 10,
            'instrument_to_usd_rate' => 0.0033,
            'fx_rate_source' => 'User-entered bank rate',
        ]);

        $this->assertSame(606.0, $result['recommended_lot']);
        $this->assertLessThanOrEqual(10.0, $result['actual_risk']);
        $this->assertSame('LKR', $result['fx_conversion']['instrument_currency']);
    }

    public function test_stock_sell_requires_explicit_short_selling_support(): void
    {
        $this->expectException(DomainException::class);
        app(StockRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'AAPL',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
            'side' => 'SELL',
            'entry' => 100,
            'stop_loss' => 105,
            'risk_amount' => 100,
        ]);
    }

    public function test_stock_affordability_caps_whole_shares_without_exceeding_risk(): void
    {
        $result = app(StockRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'AAPL',
            'company_name' => 'Apple Inc.',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
            'currency' => 'USD',
            'side' => 'BUY',
            'entry' => 200,
            'stop_loss' => 190,
            'risk_amount' => 1000,
            'buying_power' => 2100,
        ]);

        $this->assertSame(10.0, $result['recommended_lot']);
        $this->assertSame(2000.0, $result['required_investment']);
        $this->assertLessThanOrEqual(1000.0, $result['actual_risk']);
    }

    public function test_non_usd_stock_requires_an_explicit_fx_rate_and_source(): void
    {
        $this->expectException(DomainException::class);
        app(StockRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'RELIANCE',
            'region' => 'INDIA',
            'exchange' => 'NSE',
            'currency' => 'INR',
            'side' => 'BUY',
            'entry' => 2500,
            'stop_loss' => 2450,
            'risk_amount' => 100,
        ]);
    }

    public function test_valid_forex_sell_uses_inverse_price_structure(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'side' => 'SELL',
            'entry' => 1.1000,
            'stop_loss' => 1.1050,
            'take_profit' => 1.0900,
            'risk_amount' => 100,
        ]);

        $this->assertSame('SELL', $result['side']);
        $this->assertLessThanOrEqual(100.0, $result['actual_risk']);
        $this->assertEqualsWithDelta(2.0, $result['risk_reward_ratio'], 0.000001);
    }

    public function test_dividend_calculator_labels_repeated_projection_and_net_amount(): void
    {
        $result = app(DividendCalculatorService::class)->calculate([
            'symbol' => 'AAPL',
            'currency' => 'USD',
            'shares' => 100,
            'share_price' => 200,
            'dividend_per_share' => 0.25,
            'payments_per_year' => 4,
            'withholding_tax_percent' => 10,
            'fees_per_payment' => 1,
            'repeated_payment_assumption' => true,
        ]);

        $this->assertSame(100.0, $result['projected_annual_gross']);
        $this->assertSame(86.0, $result['projected_annual_net']);
        $this->assertSame('MANUAL_ESTIMATE', $result['status']);
        $this->assertTrue($result['repeated_payment_assumption']);
    }

    public function test_dividend_calculator_does_not_invent_future_payments(): void
    {
        $result = app(DividendCalculatorService::class)->calculate([
            'symbol' => 'AAPL',
            'currency' => 'USD',
            'shares' => 100,
            'share_price' => 200,
            'dividend_per_share' => 0.25,
            'payments_per_year' => 4,
            'withholding_tax_percent' => 0,
            'fees_per_payment' => 0,
            'repeated_payment_assumption' => false,
        ]);

        $this->assertSame(25.0, $result['projected_annual_gross']);
        $this->assertSame(25.0, $result['projected_annual_net']);
        $this->assertStringContainsString('No future dividend declaration', $result['warnings'][0]);
    }

    public function test_futures_result_warns_when_live_liquidation_tier_is_missing(): void
    {
        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];
        $result = app(BinanceFuturesRiskRewardCalculatorService::class)->calculate([
            'symbol' => 'BTCUSDT',
            'side' => 'BUY',
            'entry' => 100000,
            'stop_loss' => 99000,
            'risk_amount' => 100,
            'calculation_mode' => 'RISK_BASED',
            'margin_mode' => 'ISOLATED',
            'leverage' => 10,
        ], $market);

        $this->assertFalse($result['futures']['liquidation']['available']);
        $this->assertTrue(collect($result['warnings'])->contains(
            fn (string $warning): bool => str_contains($warning, 'Maintenance Margin Rate')
        ));
    }
}
