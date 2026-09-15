<?php

namespace App\Services\Trading;

use App\Models\TradingRoutinePreference;
use Carbon\CarbonInterface;

class TradingRoutinePlannerService
{
    /** @return array<string, mixed> */
    public function build(
        TradingRoutinePreference $preference,
        CarbonInterface $localDate
    ): array {
        $balance = max(0.01, (float) $preference->planning_balance);
        $dailyCapPercent = min(
            (float) $preference->daily_risk_cap_percent,
            (float) config('trading_routine.guardrails.max_daily_risk_cap_percent', 2.0)
        );
        $riskPercent = min(
            (float) $preference->risk_per_trade_percent,
            (float) config('trading_routine.guardrails.max_risk_per_trade_percent', 1.0),
            $dailyCapPercent
        );
        $riskAmount = $this->floorMoney($balance * ($riskPercent / 100));
        $dailyRiskCap = $this->floorMoney($balance * ($dailyCapPercent / 100));
        $requestedTrades = min(
            max(1, (int) $preference->max_trades),
            (int) config('trading_routine.guardrails.max_trades', 5)
        );
        $capacityTrades = $riskAmount > 0
            ? max(1, (int) floor(($dailyRiskCap + 0.000001) / $riskAmount))
            : 1;
        $maxTrades = min($requestedTrades, $capacityTrades);
        $lossStop = min(
            max(1, (int) $preference->max_consecutive_losses),
            (int) config('trading_routine.guardrails.max_consecutive_losses', 3),
            $maxTrades
        );
        $minimumRr = max(
            (float) config('trading_routine.guardrails.minimum_rr_floor', 1.0),
            (float) $preference->minimum_rr
        );
        $plannedExposure = $this->floorMoney($riskAmount * $maxTrades);
        $targetPerWinner = $this->floorMoney($riskAmount * $minimumRr);

        $markets = $this->stringList(
            $preference->markets_json,
            (array) config('trading_routine.defaults.markets', ['FOREX'])
        );
        $sessions = $this->stringList(
            $preference->sessions_json,
            (array) config('trading_routine.defaults.sessions', ['LONDON', 'NEW_YORK'])
        );
        $checklist = $this->stringList(
            $preference->pre_market_checklist_json,
            (array) config('trading_routine.default_pre_market_checklist', [])
        );
        $noTradeRules = $this->stringList(
            $preference->no_trade_rules_json,
            (array) config('trading_routine.default_no_trade_rules', [])
        );
        $focus = trim((string) ($preference->focus_statement
            ?: config('trading_routine.default_focus_statement', 'Protect capital first.')));

        return [
            'plan_date' => $localDate->format('Y-m-d'),
            'timezone' => $preference->timezone,
            'markets' => $markets,
            'sessions' => $sessions,
            'planning_balance' => $this->floorMoney($balance),
            'risk_per_trade_percent' => round($riskPercent, 4),
            'risk_per_trade_amount' => $riskAmount,
            'daily_risk_cap_percent' => round($dailyCapPercent, 4),
            'daily_risk_cap_amount' => $dailyRiskCap,
            'max_trades' => $maxTrades,
            'requested_max_trades' => $requestedTrades,
            'max_consecutive_losses' => $lossStop,
            'minimum_rr' => round($minimumRr, 2),
            'target_per_winning_trade' => $targetPerWinner,
            'maximum_planned_loss_exposure' => min($dailyRiskCap, $plannedExposure),
            'pre_market_checklist' => $checklist,
            'no_trade_rules' => $noTradeRules,
            'focus_statement' => $focus,
            'routine_steps' => [
                'Before charts: complete the pre-market checklist and confirm you are fit to trade.',
                'Trade only the selected markets and sessions in this plan.',
                sprintf('Risk no more than $%s per trade and never increase risk after a loss.', number_format($riskAmount, 2, '.', ',')),
                sprintf('Stop for the day after %d consecutive loss%s or $%s total planned loss, whichever comes first.', $lossStop, $lossStop === 1 ? '' : 'es', number_format($dailyRiskCap, 2, '.', ',')),
                'After every trade: record the setup, screenshot, emotion and rule compliance in the TradeX journal.',
                'End of session: stop when the plan is complete. Do not chase a missed target or add an unplanned trade.',
            ],
            'headline' => sprintf(
                'Today: max %d trade%s, $%s risk per trade, $%s daily risk cap, minimum R:R 1:%s.',
                $maxTrades,
                $maxTrades === 1 ? '' : 's',
                number_format($riskAmount, 2, '.', ','),
                number_format($dailyRiskCap, 2, '.', ','),
                number_format($minimumRr, 2, '.', '')
            ),
            'warnings' => $requestedTrades > $maxTrades
                ? ['Maximum trades were reduced so planned risk does not exceed the daily risk cap.']
                : [],
            'disclaimer' => (string) config('trading_routine.disclaimer'),
        ];
    }

    /** @return array<int, string> */
    private function stringList(mixed $value, array $fallback): array
    {
        $items = is_array($value) && $value !== [] ? $value : $fallback;

        return array_values(array_slice(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $items
        ), static fn (string $item): bool => $item !== ''), 0, 12));
    }

    private function floorMoney(float $value): float
    {
        return floor(($value + 0.0000001) * 100) / 100;
    }
}
