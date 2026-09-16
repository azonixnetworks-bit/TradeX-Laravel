<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\RiskRewardCalculatorService;
use DomainException;
use Tests\TestCase;

class RiskRewardCalculatorServiceTest extends TestCase
{
    private RiskRewardCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(RiskRewardCalculatorService::class);
    }

    public function test_it_calculates_eurusd_buy_position_size_and_profit(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'side' => 'BUY',
            'entry' => 1.10000,
            'stop_loss' => 1.09500,
            'take_profit' => 1.11000,
            'risk_amount' => 100,
        ]);

        $this->assertEqualsWithDelta(0.20, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(100.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(200.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(20000.00, $result['position_units'], 0.01);
        $this->assertEqualsWithDelta(100.00, $result['risk_utilization_percent'], 0.001);
        $this->assertEqualsWithDelta(2.00, $result['risk_reward_ratio'], 0.000001);
        $this->assertEqualsWithDelta(50.00, $result['stop_loss_distance'], 0.001);
        $this->assertEqualsWithDelta(100.00, $result['take_profit_distance'], 0.001);
        $this->assertTrue($result['tradable_within_risk']);
    }

    public function test_it_converts_usd_base_forex_risk_at_stop_and_profit_at_target(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'forex',
            'symbol' => 'USDJPY',
            'side' => 'BUY',
            'entry' => 150.00,
            'stop_loss' => 149.50,
            'take_profit' => 151.00,
            'risk_amount' => 100,
        ]);

        $this->assertEqualsWithDelta(0.29, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(96.99, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(192.05, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(29000.00, $result['position_units'], 0.01);
        $this->assertEqualsWithDelta(96.99, $result['risk_utilization_percent'], 0.02);
        $this->assertLessThanOrEqual(100.00, $result['actual_risk']);
    }

    public function test_it_calculates_gold_with_configured_contract_size(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'commodity',
            'symbol' => 'XAUUSD',
            'side' => 'BUY',
            'entry' => 2000,
            'stop_loss' => 1990,
            'take_profit' => 2020,
            'risk_amount' => 100,
        ]);

        $this->assertEqualsWithDelta(0.10, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(100.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(200.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(10.00, $result['position_units'], 0.001);
        $this->assertEqualsWithDelta(100.00, $result['risk_utilization_percent'], 0.001);
    }

    public function test_it_calculates_crypto_reference_profile(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'crypto',
            'symbol' => 'BTCUSD',
            'side' => 'BUY',
            'entry' => 60000,
            'stop_loss' => 59000,
            'take_profit' => 62000,
            'risk_amount' => 100,
        ]);

        $this->assertEqualsWithDelta(0.10, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(100.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(200.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(0.10, $result['position_units'], 0.000001);
    }

    public function test_it_calculates_deriv_reference_profile(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'deriv',
            'symbol' => 'BOOM1000',
            'side' => 'BUY',
            'entry' => 1000,
            'stop_loss' => 995,
            'take_profit' => 1010,
            'risk_amount' => 5,
        ]);

        $this->assertEqualsWithDelta(1.00, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(5.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(10.00, $result['potential_profit'], 0.01);
        $this->assertEqualsWithDelta(1.00, $result['position_units'], 0.000001);
        $this->assertEqualsWithDelta(100.00, $result['risk_utilization_percent'], 0.001);
    }

    public function test_it_does_not_force_minimum_lot_when_it_would_exceed_requested_risk(): void
    {
        $result = $this->calculator->calculate([
            'market' => 'deriv',
            'symbol' => 'BOOM1000',
            'side' => 'BUY',
            'entry' => 1000,
            'stop_loss' => 950,
            'take_profit' => 1100,
            'risk_amount' => 1,
        ]);

        $this->assertNull($result['recommended_lot']);
        $this->assertNull($result['actual_risk']);
        $this->assertNull($result['position_units']);
        $this->assertNull($result['risk_utilization_percent']);
        $this->assertFalse($result['tradable_within_risk']);
        $this->assertEqualsWithDelta(10.00, $result['minimum_lot_risk'], 0.01);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_it_rejects_invalid_buy_price_structure(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('For a BUY trade');

        $this->calculator->calculate([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'side' => 'BUY',
            'entry' => 1.10000,
            'stop_loss' => 1.10500,
            'take_profit' => 1.11000,
            'risk_amount' => 100,
        ]);
    }

    public function test_it_rejects_invalid_sell_price_structure(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('For a SELL trade');

        $this->calculator->calculate([
            'market' => 'forex',
            'symbol' => 'EURUSD',
            'side' => 'SELL',
            'entry' => 1.10000,
            'stop_loss' => 1.09500,
            'take_profit' => 1.09000,
            'risk_amount' => 100,
        ]);
    }
}
