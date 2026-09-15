<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\RiskRewardCalculatorService;
use Tests\TestCase;

class IcMarketsCrossPairRiskRewardTest extends TestCase
{
    public function test_cross_pair_can_use_manual_quote_currency_to_usd_rate(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'forex',
            'symbol' => 'EURGBP',
            'side' => 'BUY',
            'entry' => 0.8500,
            'stop_loss' => 0.8450,
            'take_profit' => 0.8600,
            'risk_amount' => 100,
            'quote_to_usd_rate' => 1.30,
        ]);

        $this->assertEqualsWithDelta(0.15, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(97.50, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(195.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(15000.00, $result['position_units'], 0.01);
        $this->assertEqualsWithDelta(97.50, $result['risk_utilization_percent'], 0.01);
        $this->assertSame('MANUAL', $result['quote_to_usd_conversion']['source']);
        $this->assertEqualsWithDelta(1.30, $result['quote_to_usd_conversion']['risk_rate'], 0.000001);
        $this->assertSame('IC Markets', $result['specification']['broker']);
        $this->assertSame('Minor', $result['specification']['category']);
    }

    public function test_usd_base_pair_uses_trade_price_for_usd_conversion_without_external_rate(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'forex',
            'symbol' => 'USDJPY',
            'side' => 'BUY',
            'entry' => 150.00,
            'stop_loss' => 149.50,
            'take_profit' => 151.00,
            'risk_amount' => 100,
        ]);

        $this->assertSame('INSTRUMENT_PRICE', $result['quote_to_usd_conversion']['source']);
        $this->assertSame('USDJPY', $result['quote_to_usd_conversion']['symbol']);
    }
}
