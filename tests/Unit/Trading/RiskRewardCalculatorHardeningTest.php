<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\RiskRewardCalculatorService;
use Tests\TestCase;

class RiskRewardCalculatorHardeningTest extends TestCase
{
    public function test_crash_1000_sell_position_uses_reviewed_deriv_size_profile(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'deriv',
            'symbol' => 'CRASH1000',
            'side' => 'SELL',
            'entry' => 1000,
            'stop_loss' => 1005,
            'take_profit' => 990,
            'risk_amount' => 10,
        ]);

        $this->assertEqualsWithDelta(2.0, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(10.0, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(20.0, $result['potential_profit'], 0.01);
        $this->assertSame(720.0, $result['specification']['max_lot']);
        $this->assertSame('PARTIALLY VERIFIED', $result['specification']['status']);
        $this->assertSame('2026-09-15', $result['specification']['verified_at']);
    }

    public function test_position_is_capped_at_configured_maximum_volume(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'deriv',
            'symbol' => 'BOOM300',
            'side' => 'BUY',
            'entry' => 1000,
            'stop_loss' => 999,
            'take_profit' => 1002,
            'risk_amount' => 100000,
        ]);

        $this->assertEqualsWithDelta(475.0, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(475.0, $result['actual_risk'], 0.01);
        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('capped', strtolower(implode(' ', $result['warnings'])));
    }

    public function test_reference_crypto_profile_is_labeled_as_reference_not_verified(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'crypto',
            'symbol' => 'ETHUSD',
            'side' => 'BUY',
            'entry' => 4000,
            'stop_loss' => 3900,
            'take_profit' => 4200,
            'risk_amount' => 100,
        ]);

        $this->assertSame('REFERENCE', $result['specification']['status']);
        $this->assertNull($result['specification']['verified_at']);
        $this->assertStringContainsString(
            'broker-neutral',
            strtolower($result['specification']['source'])
        );
    }
}