<?php

namespace Tests\Unit\Trading;

use App\Models\TradingRoutinePreference;
use App\Services\Trading\TradingRoutinePlannerService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TradingRoutinePlannerServiceTest extends TestCase
{
    public function test_daily_risk_cap_can_reduce_maximum_trades(): void
    {
        $preference = new TradingRoutinePreference([
            'timezone' => 'Asia/Colombo',
            'planning_balance' => 10000,
            'risk_per_trade_percent' => 0.5,
            'daily_risk_cap_percent' => 1.0,
            'max_trades' => 5,
            'max_consecutive_losses' => 3,
            'minimum_rr' => 2,
            'markets_json' => ['FOREX'],
            'sessions_json' => ['LONDON'],
        ]);

        $plan = app(TradingRoutinePlannerService::class)->build(
            $preference,
            CarbonImmutable::parse('2026-09-15', 'Asia/Colombo')
        );

        $this->assertSame(50.0, $plan['risk_per_trade_amount']);
        $this->assertSame(100.0, $plan['daily_risk_cap_amount']);
        $this->assertSame(2, $plan['max_trades']);
        $this->assertSame(2, $plan['max_consecutive_losses']);
        $this->assertSame(100.0, $plan['maximum_planned_loss_exposure']);
        $this->assertNotEmpty($plan['warnings']);
    }

    public function test_service_clamps_risk_to_tradex_guardrail(): void
    {
        config()->set('trading_routine.guardrails.max_risk_per_trade_percent', 1.0);

        $preference = new TradingRoutinePreference([
            'timezone' => 'Asia/Colombo',
            'planning_balance' => 20000,
            'risk_per_trade_percent' => 3.0,
            'daily_risk_cap_percent' => 2.0,
            'max_trades' => 2,
            'max_consecutive_losses' => 2,
            'minimum_rr' => 2,
        ]);

        $plan = app(TradingRoutinePlannerService::class)->build(
            $preference,
            CarbonImmutable::parse('2026-09-15', 'Asia/Colombo')
        );

        $this->assertSame(1.0, $plan['risk_per_trade_percent']);
        $this->assertSame(200.0, $plan['risk_per_trade_amount']);
        $this->assertStringContainsString('never increase risk after a loss', implode(' ', $plan['routine_steps']));
    }
}
