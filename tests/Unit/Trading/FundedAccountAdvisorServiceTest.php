<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\FundedAccountAdvisorService;
use Tests\TestCase;

class FundedAccountAdvisorServiceTest extends TestCase
{
    public function test_safe_account_gets_clear_reduced_loss_sequence_plan(): void
    {
        $result = app(FundedAccountAdvisorService::class)->advise($this->input());

        $this->assertSame('SAFE', $result['status']);
        $this->assertSame(250.0, $result['recommended_risk_amount']);
        $this->assertSame(0.25, $result['recommended_risk_percent']);
        $this->assertSame(3, $result['recommended_entries']);
        $this->assertSame(600.0, $result['planned_daily_exposure']);
        $this->assertSame(5000.0, $result['daily_loss_remaining']);
        $this->assertSame(10000.0, $result['overall_drawdown_remaining']);
        $this->assertSame(500.0, $result['target_profit_per_win']);
        $this->assertSame(20, $result['estimated_wins_to_target']);
        $this->assertSame([250.0, 200.0, 150.0], array_column($result['entry_plan'], 'risk_amount'));
    }

    public function test_requested_risk_is_capped_by_tradex_policy_and_never_martingales(): void
    {
        $input = $this->input([
            'desired_risk_percent' => 1.0,
            'current_balance' => 98000,
            'current_equity' => 98000,
        ]);

        $result = app(FundedAccountAdvisorService::class)->advise($input);

        $this->assertSame('CAUTION', $result['status']);
        $this->assertSame(500.0, $result['recommended_risk_amount']);
        $this->assertSame(2, $result['recommended_entries']);
        $this->assertSame([500.0, 400.0], array_column($result['entry_plan'], 'risk_amount'));
        $this->assertLessThanOrEqual(
            $result['entry_plan'][0]['risk_amount'],
            $result['entry_plan'][1]['risk_amount']
        );
    }

    public function test_danger_zone_returns_zero_new_trade_risk(): void
    {
        $input = $this->input([
            'current_balance' => 96200,
            'current_equity' => 96200,
        ]);

        $result = app(FundedAccountAdvisorService::class)->advise($input);

        $this->assertSame('DANGER', $result['status']);
        $this->assertSame(0.0, $result['recommended_risk_amount']);
        $this->assertSame(0, $result['recommended_entries']);
        $this->assertSame([], $result['entry_plan']);
        $this->assertStringContainsString('Do not open', $result['headline']);
    }

    public function test_trailing_drawdown_uses_supplied_high_water_reference(): void
    {
        $input = $this->input([
            'drawdown_type' => 'TRAILING',
            'trailing_reference_amount' => 105000,
            'current_balance' => 100000,
            'current_equity' => 100000,
        ]);

        $result = app(FundedAccountAdvisorService::class)->advise($input);

        $this->assertSame(95000.0, $result['drawdown_threshold']);
        $this->assertSame(5000.0, $result['overall_drawdown_remaining']);
    }

    public function test_reached_profit_target_stops_new_challenge_risk(): void
    {
        $input = $this->input([
            'current_balance' => 110000,
            'current_equity' => 110000,
        ]);

        $result = app(FundedAccountAdvisorService::class)->advise($input);

        $this->assertSame('TARGET_REACHED', $result['status']);
        $this->assertSame(0.0, $result['recommended_risk_amount']);
        $this->assertSame(0, $result['recommended_entries']);
        $this->assertSame(0, $result['estimated_wins_to_target']);
    }

    public function test_funded_account_can_have_no_profit_target_without_forcing_stop(): void
    {
        $input = $this->input([
            'phase' => 'FUNDED',
            'profit_target_percent' => 0,
        ]);

        $result = app(FundedAccountAdvisorService::class)->advise($input);

        $this->assertSame('SAFE', $result['status']);
        $this->assertSame(250.0, $result['recommended_risk_amount']);
        $this->assertNull($result['estimated_wins_to_target']);
    }

    /** @param array<string, mixed> $overrides */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'phase' => 'EVALUATION',
            'account_size' => 100000,
            'profit_target_percent' => 10,
            'daily_loss_limit_percent' => 5,
            'max_drawdown_percent' => 10,
            'daily_loss_basis' => 'EQUITY',
            'daily_start_reference' => 100000,
            'drawdown_type' => 'STATIC',
            'drawdown_basis' => 'EQUITY',
            'trailing_reference_amount' => null,
            'current_balance' => 100000,
            'current_equity' => 100000,
            'desired_risk_percent' => 0.25,
            'planned_max_entries' => 3,
            'minimum_rr' => 2,
            'minimum_trading_days' => 5,
            'completed_trading_days' => 1,
        ], $overrides);
    }
}
