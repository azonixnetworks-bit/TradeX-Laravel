<?php

namespace App\Services\Trading;

use InvalidArgumentException;

class FundedAccountAdvisorService
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function advise(array $input): array
    {
        $accountSize = $this->positive($input, 'account_size');
        $currentBalance = $this->positive($input, 'current_balance');
        $currentEquity = $this->positive($input, 'current_equity');
        $dailyStartReference = $this->positive($input, 'daily_start_reference');
        $profitTargetPercent = $this->nonNegative($input, 'profit_target_percent');
        $dailyLossLimitPercent = $this->positive($input, 'daily_loss_limit_percent');
        $maxDrawdownPercent = $this->positive($input, 'max_drawdown_percent');
        $desiredRiskPercent = $this->positive($input, 'desired_risk_percent');
        $plannedMaxEntries = max(1, (int) ($input['planned_max_entries'] ?? 1));
        $minimumRr = $this->positive($input, 'minimum_rr');

        $dailyLossBasis = strtoupper((string) ($input['daily_loss_basis'] ?? 'EQUITY'));
        $drawdownBasis = strtoupper((string) ($input['drawdown_basis'] ?? 'EQUITY'));
        $drawdownType = strtoupper((string) ($input['drawdown_type'] ?? 'STATIC'));

        if (! in_array($dailyLossBasis, ['BALANCE', 'EQUITY'], true)) {
            throw new InvalidArgumentException('Unsupported daily loss basis.');
        }

        if (! in_array($drawdownBasis, ['BALANCE', 'EQUITY'], true)) {
            throw new InvalidArgumentException('Unsupported drawdown basis.');
        }

        if (! in_array($drawdownType, ['STATIC', 'TRAILING'], true)) {
            throw new InvalidArgumentException('Unsupported drawdown type.');
        }

        $dailyCurrentReference = $dailyLossBasis === 'EQUITY'
            ? $currentEquity
            : $currentBalance;
        $drawdownCurrentReference = $drawdownBasis === 'EQUITY'
            ? $currentEquity
            : $currentBalance;

        $dailyLimitAmount = $dailyStartReference * ($dailyLossLimitPercent / 100);
        $dailyLossUsed = max(0.0, $dailyStartReference - $dailyCurrentReference);
        $dailyRemaining = max(0.0, $dailyLimitAmount - $dailyLossUsed);
        $dailyUsedPercent = $dailyLimitAmount > 0
            ? ($dailyLossUsed / $dailyLimitAmount) * 100
            : 100.0;

        $maxDrawdownAmount = $accountSize * ($maxDrawdownPercent / 100);
        $trailingReference = $drawdownType === 'TRAILING'
            ? $this->positive($input, 'trailing_reference_amount')
            : $accountSize;
        $drawdownThreshold = $trailingReference - $maxDrawdownAmount;
        $overallRemaining = max(0.0, $drawdownCurrentReference - $drawdownThreshold);
        $overallCapacityForUsage = min($maxDrawdownAmount, $overallRemaining);
        $overallDrawdownUsed = max(0.0, $maxDrawdownAmount - $overallCapacityForUsage);
        $overallUsedPercent = $maxDrawdownAmount > 0
            ? ($overallDrawdownUsed / $maxDrawdownAmount) * 100
            : 100.0;

        $targetAmount = $accountSize * ($profitTargetPercent / 100);
        $targetBalance = $accountSize + $targetAmount;
        $currentProfit = $currentBalance - $accountSize;
        $remainingTarget = $targetAmount > 0
            ? max(0.0, $targetBalance - $currentBalance)
            : 0.0;
        $targetProgress = $targetAmount > 0
            ? max(0.0, min(100.0, ($currentProfit / $targetAmount) * 100))
            : 0.0;
        $targetReached = $targetAmount > 0 && $remainingTarget <= 0.000001;

        $dangerThreshold = (float) config(
            'funded_account_advisor.status_thresholds.danger_used_percent',
            75.0
        );
        $hardStop = $dailyRemaining <= 0
            || $overallRemaining <= 0
            || $dailyUsedPercent >= $dangerThreshold
            || $overallUsedPercent >= $dangerThreshold
            || $targetReached;

        $reservePercent = min(
            95.0,
            max(0.0, (float) config('funded_account_advisor.safety_reserve_percent', 20.0))
        );
        $reserveFactor = 1 - ($reservePercent / 100);
        $safeDailyRemaining = $dailyRemaining * $reserveFactor;
        $safeOverallRemaining = $overallRemaining * $reserveFactor;

        $maxRecommendedRiskPercent = max(
            0.01,
            (float) config('funded_account_advisor.max_recommended_risk_percent', 0.50)
        );
        $maxDailyPlanPercent = max(
            0.01,
            (float) config('funded_account_advisor.max_daily_plan_percent', 1.00)
        );

        $desiredRiskAmount = $accountSize * ($desiredRiskPercent / 100);
        $policyRiskCap = $accountSize * ($maxRecommendedRiskPercent / 100);
        $perEntryDailyCap = $safeDailyRemaining / $plannedMaxEntries;
        $perEntryOverallCap = $safeOverallRemaining / $plannedMaxEntries;

        $recommendedRisk = $hardStop
            ? 0.0
            : $this->floorMoney(max(0.0, min(
                $desiredRiskAmount,
                $policyRiskCap,
                $perEntryDailyCap,
                $perEntryOverallCap
            )));

        $policyDailyCap = $accountSize * ($maxDailyPlanPercent / 100);
        $dailyPlanBudget = $hardStop
            ? 0.0
            : $this->floorMoney(max(0.0, min(
                $policyDailyCap,
                $safeDailyRemaining,
                $safeOverallRemaining
            )));

        $entryPlan = $this->buildEntryPlan(
            $recommendedRisk,
            $plannedMaxEntries,
            $dailyPlanBudget
        );
        $recommendedEntries = count($entryPlan);
        $plannedExposure = $this->floorMoney(array_sum(array_column($entryPlan, 'risk_amount')));

        $targetPerWinningTrade = $this->floorMoney($recommendedRisk * $minimumRr);
        $estimatedWinsToTarget = $targetAmount <= 0
            ? null
            : ($targetReached
                ? 0
                : ($targetPerWinningTrade > 0
                    ? (int) ceil($remainingTarget / $targetPerWinningTrade)
                    : null));

        $lossCapacity = $recommendedRisk > 0
            ? (int) floor(min($dailyRemaining, $overallRemaining) / $recommendedRisk)
            : 0;

        $dailyStopReference = $this->floorMoney(max(
            0.0,
            $dailyCurrentReference - $dailyPlanBudget
        ));

        $status = $this->status(
            $dailyRemaining,
            $overallRemaining,
            $dailyUsedPercent,
            $overallUsedPercent,
            $recommendedRisk,
            $desiredRiskAmount,
            $targetReached
        );

        $minimumTradingDays = max(0, (int) ($input['minimum_trading_days'] ?? 0));
        $completedTradingDays = max(0, (int) ($input['completed_trading_days'] ?? 0));

        return [
            'status' => $status,
            'recommended_risk_amount' => $recommendedRisk,
            'recommended_risk_percent' => $accountSize > 0
                ? round(($recommendedRisk / $accountSize) * 100, 4)
                : 0.0,
            'requested_risk_percent' => round($desiredRiskPercent, 4),
            'max_daily_plan_amount' => $dailyPlanBudget,
            'planned_daily_exposure' => $plannedExposure,
            'recommended_entries' => $recommendedEntries,
            'requested_max_entries' => $plannedMaxEntries,
            'daily_stop_reference' => $dailyStopReference,
            'daily_loss_basis' => $dailyLossBasis,
            'daily_limit_amount' => $this->floorMoney($dailyLimitAmount),
            'daily_loss_used' => $this->floorMoney($dailyLossUsed),
            'daily_loss_remaining' => $this->floorMoney($dailyRemaining),
            'daily_loss_used_percent' => round($dailyUsedPercent, 2),
            'drawdown_type' => $drawdownType,
            'drawdown_basis' => $drawdownBasis,
            'drawdown_threshold' => $this->floorMoney($drawdownThreshold),
            'max_drawdown_amount' => $this->floorMoney($maxDrawdownAmount),
            'overall_drawdown_used' => $this->floorMoney($overallDrawdownUsed),
            'overall_drawdown_remaining' => $this->floorMoney($overallRemaining),
            'overall_drawdown_used_percent' => round($overallUsedPercent, 2),
            'losses_before_rule_capacity' => $lossCapacity,
            'minimum_rr' => round($minimumRr, 2),
            'target_profit_per_win' => $targetPerWinningTrade,
            'profit_target_amount' => $this->floorMoney($targetAmount),
            'target_balance' => $this->floorMoney($targetBalance),
            'current_profit' => $this->floorMoney($currentProfit),
            'remaining_profit_target' => $this->floorMoney($remainingTarget),
            'target_progress_percent' => round($targetProgress, 2),
            'estimated_wins_to_target' => $estimatedWinsToTarget,
            'minimum_trading_days' => $minimumTradingDays,
            'completed_trading_days' => $completedTradingDays,
            'remaining_minimum_trading_days' => max(0, $minimumTradingDays - $completedTradingDays),
            'safety_reserve_percent' => round($reservePercent, 2),
            'entry_plan' => $entryPlan,
            'headline' => $this->headline(
                $status,
                $recommendedEntries,
                $recommendedRisk,
                $plannedExposure,
                $minimumRr
            ),
            'warnings' => $this->warnings(
                $status,
                $recommendedRisk,
                $desiredRiskAmount,
                $dailyRemaining,
                $overallRemaining,
                $recommendedEntries
            ),
            'disclaimer' => (string) config('funded_account_advisor.disclaimer'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function buildEntryPlan(
        float $baseRisk,
        int $maxEntries,
        float $dailyBudget
    ): array {
        if ($baseRisk <= 0 || $dailyBudget <= 0) {
            return [];
        }

        $factors = array_values(array_filter(
            (array) config('funded_account_advisor.loss_sequence_factors', [1.0, 0.8, 0.6, 0.5]),
            static fn (mixed $factor): bool => is_numeric($factor) && (float) $factor > 0
        ));

        if ($factors === []) {
            $factors = [1.0];
        }

        $lastFactor = (float) end($factors);
        $used = 0.0;
        $plan = [];

        for ($index = 0; $index < $maxEntries; $index++) {
            $factor = (float) ($factors[$index] ?? $lastFactor);
            $risk = $this->floorMoney($baseRisk * min(1.0, $factor));

            if ($risk <= 0 || ($used + $risk) > ($dailyBudget + 0.000001)) {
                break;
            }

            $used += $risk;
            $plan[] = [
                'entry' => $index + 1,
                'risk_amount' => $risk,
                'risk_factor' => round(min(1.0, $factor), 2),
                'instruction' => $index === 0
                    ? 'Use only if the setup meets your plan.'
                    : 'Use only after the previous planned trade is closed; risk is reduced after a loss.',
            ];
        }

        return $plan;
    }

    private function status(
        float $dailyRemaining,
        float $overallRemaining,
        float $dailyUsedPercent,
        float $overallUsedPercent,
        float $recommendedRisk,
        float $desiredRiskAmount,
        bool $targetReached
    ): string {
        $danger = (float) config(
            'funded_account_advisor.status_thresholds.danger_used_percent',
            75.0
        );
        $caution = (float) config(
            'funded_account_advisor.status_thresholds.caution_used_percent',
            50.0
        );

        if (
            $dailyRemaining <= 0
            || $overallRemaining <= 0
            || $dailyUsedPercent >= $danger
            || $overallUsedPercent >= $danger
        ) {
            return 'DANGER';
        }

        if ($targetReached) {
            return 'TARGET_REACHED';
        }

        if ($recommendedRisk <= 0) {
            return 'DANGER';
        }

        if (
            $dailyUsedPercent >= $caution
            || $overallUsedPercent >= $caution
            || $recommendedRisk + 0.009 < $desiredRiskAmount
        ) {
            return 'CAUTION';
        }

        return 'SAFE';
    }

    private function headline(
        string $status,
        int $entries,
        float $risk,
        float $exposure,
        float $rr
    ): string {
        if ($status === 'TARGET_REACHED') {
            return 'Profit target reached. Do not add challenge risk just to keep trading; verify the provider dashboard and progression requirements.';
        }

        if ($entries <= 0 || $risk <= 0) {
            return 'Do not open a new planned entry. The current risk capacity is insufficient or inside the TradeX stop-trading zone.';
        }

        return sprintf(
            'Plan up to %d entr%s. First-entry risk: $%s. Planned loss exposure: $%s. Minimum target R:R: 1:%s.',
            $entries,
            $entries === 1 ? 'y' : 'ies',
            number_format($risk, 2, '.', ','),
            number_format($exposure, 2, '.', ','),
            number_format($rr, 2, '.', '')
        );
    }

    /** @return array<int, string> */
    private function warnings(
        string $status,
        float $recommendedRisk,
        float $desiredRiskAmount,
        float $dailyRemaining,
        float $overallRemaining,
        int $recommendedEntries
    ): array {
        $warnings = [];

        if ($status === 'DANGER') {
            $warnings[] = 'Risk capacity is in the danger zone. Do not force a trade; verify the provider dashboard and current rule calculations before any new entry.';
        } elseif ($status === 'CAUTION') {
            $warnings[] = 'Risk capacity is reduced. Use the advisor amount rather than the requested risk amount.';
        } elseif ($status === 'TARGET_REACHED') {
            $warnings[] = 'The configured profit target is reached. Verify minimum-day and progression requirements before taking additional risk.';
        }

        if ($recommendedRisk > 0 && $recommendedRisk + 0.009 < $desiredRiskAmount) {
            $warnings[] = 'Requested risk was reduced to protect the configured daily and overall drawdown buffers.';
        }

        if ($dailyRemaining < $overallRemaining) {
            $warnings[] = 'The daily loss rule is currently the tighter firm constraint.';
        } elseif ($overallRemaining < $dailyRemaining) {
            $warnings[] = 'The overall drawdown rule is currently the tighter firm constraint.';
        }

        if ($recommendedEntries === 0) {
            $warnings[] = 'No new entry is recommended under the current planning guardrails.';
        }

        return array_values(array_unique($warnings));
    }

    /** @param array<string, mixed> $input */
    private function positive(array $input, string $key): float
    {
        $value = (float) ($input[$key] ?? 0);

        if ($value <= 0) {
            throw new InvalidArgumentException($key.' must be greater than zero.');
        }

        return $value;
    }

    /** @param array<string, mixed> $input */
    private function nonNegative(array $input, string $key): float
    {
        $value = (float) ($input[$key] ?? 0);

        if ($value < 0) {
            throw new InvalidArgumentException($key.' cannot be negative.');
        }

        return $value;
    }

    private function floorMoney(float $value): float
    {
        return floor(($value + 0.0000001) * 100) / 100;
    }
}
