<?php

namespace App\Services\Admin\Investments;

use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentSettlement;
use App\Models\InvestmentTrade;
use App\Models\InvestmentTransaction;
use App\Support\FixedDecimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class InvestmentSettlementProfitService
{
    public function earlyTerminationSnapshot(
        Investment $investment,
        InvestmentSettlement $settlement
    ): array {
        if ($settlement->type !== InvestmentSettlement::TYPE_EARLY_TERMINATION) {
            throw new DomainException('Terminal accrued-profit calculation is available only for early termination.');
        }

        $cutoff = CarbonImmutable::parse($settlement->cutoff_at);
        $this->assertPriorPeriodsFinalized($investment, $cutoff);

        $lastFinalized = $investment->profitPeriods()
            ->whereDate('period_end', '<=', $cutoff->toDateString())
            ->whereIn('status', $this->finalPeriodStatuses())
            ->orderByDesc('sequence')
            ->first();

        $windowStart = $lastFinalized
            ? CarbonImmutable::parse($lastFinalized->period_end)->addDay()->startOfDay()
            : CarbonImmutable::parse($investment->start_date)->startOfDay();

        $openingLoss = $lastFinalized
            ? FixedDecimal::normalize((string) $lastFinalized->loss_carryforward_close_usdt)
            : FixedDecimal::normalize('0');

        $referenceCapital = $this->referenceCapitalAt($investment, $cutoff);

        if ($windowStart->gt($cutoff)) {
            return $this->emptySnapshot(
                $windowStart,
                $cutoff,
                $openingLoss,
                $referenceCapital
            );
        }

        $currentPeriod = $investment->profitPeriods()
            ->whereDate('period_start', '<=', $cutoff->toDateString())
            ->whereDate('period_end', '>=', $cutoff->toDateString())
            ->first();

        if (! $currentPeriod instanceof InvestmentProfitPeriod) {
            throw new DomainException(
                'Unable to resolve the active profit-review period for the early-termination cutoff.'
            );
        }

        $this->assertTerminalLedgerReady(
            $investment,
            $settlement,
            $currentPeriod,
            $windowStart,
            $cutoff
        );

        $trades = InvestmentTrade::query()
            ->where('investment_id', $investment->getKey())
            ->where('status', InvestmentTrade::STATUS_VERIFIED)
            ->whereBetween('executed_at', [$windowStart, $cutoff])
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();

        $costs = InvestmentApprovedCost::query()
            ->where('investment_id', $investment->getKey())
            ->where('status', InvestmentApprovedCost::STATUS_VERIFIED)
            ->whereBetween('occurred_at', [$windowStart, $cutoff])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();

        $adjustments = $this->adjustmentsForTargets(
            $investment,
            $trades->pluck('id')->all(),
            $costs->pluck('id')->all(),
            InvestmentFinancialAdjustment::STATUS_VERIFIED
        );

        $pnlValues = $trades
            ->pluck('realized_pnl_usdt')
            ->map(fn ($value) => (string) $value)
            ->all();

        foreach ($adjustments as $adjustment) {
            if (! FixedDecimal::isZero((string) $adjustment->realized_pnl_delta_usdt)) {
                $pnlValues[] = (string) $adjustment->realized_pnl_delta_usdt;
            }
        }

        $grossProfit = FixedDecimal::normalize('0');
        $realizedLosses = FixedDecimal::normalize('0');

        foreach ($pnlValues as $pnl) {
            $normalized = FixedDecimal::normalize((string) $pnl);
            if (FixedDecimal::isPositive($normalized)) {
                $grossProfit = FixedDecimal::add($grossProfit, $normalized);
            } elseif (FixedDecimal::isNegative($normalized)) {
                $realizedLosses = FixedDecimal::add(
                    $realizedLosses,
                    FixedDecimal::abs($normalized)
                );
            }
        }

        $approvedCosts = FixedDecimal::sum(
            $costs->pluck('amount_usdt')
                ->map(fn ($value) => (string) $value)
                ->all()
        );

        foreach ($adjustments as $adjustment) {
            $approvedCosts = FixedDecimal::add(
                $approvedCosts,
                (string) $adjustment->approved_cost_delta_usdt
            );
        }

        if (FixedDecimal::isNegative($approvedCosts)) {
            throw new DomainException(
                'Verified settlement-window cost adjustments cannot reduce approved costs below zero.'
            );
        }

        $currentNet = FixedDecimal::sub(
            FixedDecimal::sub($grossProfit, $realizedLosses),
            $approvedCosts
        );

        $eligible = FixedDecimal::normalize('0');
        $closingLoss = $openingLoss;

        if (FixedDecimal::isNegative($currentNet)) {
            $closingLoss = FixedDecimal::add(
                $openingLoss,
                FixedDecimal::abs($currentNet)
            );
        } elseif (FixedDecimal::isPositive($currentNet)) {
            $recovery = FixedDecimal::min($currentNet, $openingLoss);
            $closingLoss = FixedDecimal::sub($openingLoss, $recovery);
            $eligible = FixedDecimal::sub($currentNet, $recovery);
        }

        $customerShare = FixedDecimal::multiplyBasisPoints(
            $eligible,
            (int) $investment->customer_profit_share_bps
        );

        $companyIndependent = FixedDecimal::multiplyBasisPoints(
            $eligible,
            (int) $investment->company_profit_share_bps
        );

        $roundingAdjustment = FixedDecimal::sub(
            $eligible,
            FixedDecimal::add($customerShare, $companyIndependent)
        );

        $companyShare = FixedDecimal::add(
            $companyIndependent,
            $roundingAdjustment
        );

        $pnlEvidence = InvestmentEvidence::query()
            ->where(
                'investment_settlement_id',
                $settlement->getKey()
            )
            ->where(
                'evidence_type',
                InvestmentEvidence::TYPE_TERMINAL_PNL_STATEMENT
            )
            ->where(
                'status',
                InvestmentEvidence::STATUS_VERIFIED
            )
            ->orderBy('id')
            ->get();

        return [
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $cutoff->toIso8601String(),
            'profit_period_id' => $currentPeriod->getKey(),
            'reference_capital_usdt' => $referenceCapital,
            'gross_realized_profit_usdt' => $grossProfit,
            'realized_losses_usdt' => $realizedLosses,
            'approved_costs_usdt' => $approvedCosts,
            'loss_carryforward_open_usdt' => $openingLoss,
            'loss_carryforward_close_usdt' => $closingLoss,
            'eligible_net_profit_usdt' => $eligible,
            'customer_profit_share_usdt' => $customerShare,
            'company_profit_share_usdt' => $companyShare,
            'rounding_adjustment_usdt' => $roundingAdjustment,
            'source' => [
                'trades' => $trades->map(fn (InvestmentTrade $trade) => [
                    'id' => $trade->getKey(),
                    'uuid' => $trade->uuid,
                    'action' => $trade->action,
                    'asset_symbol' => $trade->asset_symbol,
                    'network' => $trade->network,
                    'contract_address' => $trade->contract_address,
                    'quantity' => (string) $trade->quantity,
                    'gross_value_usdt' => (string) $trade->gross_value_usdt,
                    'fee_usdt' => (string) $trade->fee_usdt,
                    'realized_pnl_usdt' => (string) $trade->realized_pnl_usdt,
                    'executed_at' => $trade->executed_at?->toIso8601String(),
                    'verified_at' => $trade->verified_at?->toIso8601String(),
                ])->all(),
                'costs' => $costs->map(fn (InvestmentApprovedCost $cost) => [
                    'id' => $cost->getKey(),
                    'uuid' => $cost->uuid,
                    'cost_type' => $cost->cost_type,
                    'amount_usdt' => (string) $cost->amount_usdt,
                    'occurred_at' => $cost->occurred_at?->toIso8601String(),
                    'source_reference' => $cost->source_reference,
                    'verified_at' => $cost->verified_at?->toIso8601String(),
                ])->all(),
                'adjustments' => $adjustments->map(
                    fn (InvestmentFinancialAdjustment $adjustment) => [
                        'id' => $adjustment->getKey(),
                        'uuid' => $adjustment->uuid,
                        'target_type' => $adjustment->target_type,
                        'target_id' => $adjustment->target_id,
                        'adjustment_type' => $adjustment->adjustment_type,
                        'realized_pnl_delta_usdt' => (string) $adjustment->realized_pnl_delta_usdt,
                        'approved_cost_delta_usdt' => (string) $adjustment->approved_cost_delta_usdt,
                        'verified_at' => $adjustment->verified_at?->toIso8601String(),
                    ]
                )->all(),
                'pnl_evidence' => $pnlEvidence->map(
                    fn (InvestmentEvidence $evidence) => [
                        'id' => $evidence->getKey(),
                        'uuid' => $evidence->uuid,
                        'document_sha256' => $evidence->document_sha256,
                        'document_size_bytes' => $evidence->document_size_bytes,
                        'verified_at' => $evidence->verified_at?->toIso8601String(),
                    ]
                )->all(),
            ],
        ];
    }

    public function maturitySnapshot(Investment $investment): array
    {
        $this->assertAllPeriodsSettled($investment);

        $latest = $investment->profitPeriods()
            ->orderByDesc('sequence')
            ->first();

        return [
            'window_start' => null,
            'window_end' => $investment->maturity_date
                ? CarbonImmutable::parse($investment->maturity_date)->endOfDay()->toIso8601String()
                : null,
            'profit_period_id' => null,
            'reference_capital_usdt' => $this->referenceCapitalAt(
                $investment,
                CarbonImmutable::parse($investment->maturity_date)->endOfDay()
            ),
            'gross_realized_profit_usdt' => '0.00000000',
            'realized_losses_usdt' => '0.00000000',
            'approved_costs_usdt' => '0.00000000',
            'loss_carryforward_open_usdt' => $latest
                ? FixedDecimal::normalize((string) $latest->loss_carryforward_close_usdt)
                : '0.00000000',
            'loss_carryforward_close_usdt' => $latest
                ? FixedDecimal::normalize((string) $latest->loss_carryforward_close_usdt)
                : '0.00000000',
            'eligible_net_profit_usdt' => '0.00000000',
            'customer_profit_share_usdt' => '0.00000000',
            'company_profit_share_usdt' => '0.00000000',
            'rounding_adjustment_usdt' => '0.00000000',
            'source' => [
                'final_profit_periods' => $investment->profitPeriods()
                    ->orderBy('sequence')
                    ->get()
                    ->map(fn (InvestmentProfitPeriod $period) => [
                        'id' => $period->getKey(),
                        'sequence' => $period->sequence,
                        'status' => $period->status,
                        'distribution_outcome' => $period->distribution_outcome,
                        'customer_profit_share_usdt' => (string) $period->customer_profit_share_usdt,
                        'company_profit_share_usdt' => (string) $period->company_profit_share_usdt,
                        'loss_carryforward_close_usdt' => (string) $period->loss_carryforward_close_usdt,
                        'calculation_source_sha256' => $period->calculation_source_sha256,
                        'approved_at' => $period->approved_at?->toIso8601String(),
                        'paid_at' => $period->paid_at?->toIso8601String(),
                    ])->all(),
            ],
        ];
    }

    private function assertTerminalLedgerReady(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentProfitPeriod $currentPeriod,
        CarbonImmutable $windowStart,
        CarbonImmutable $cutoff
    ): void {
        $tradeIds = InvestmentTrade::query()
            ->where('investment_id', $investment->getKey())
            ->whereBetween('executed_at', [$windowStart, $cutoff])
            ->pluck('id')
            ->all();

        $costIds = InvestmentApprovedCost::query()
            ->where('investment_id', $investment->getKey())
            ->whereBetween('occurred_at', [$windowStart, $cutoff])
            ->pluck('id')
            ->all();

        $pendingTrade = InvestmentTrade::query()
            ->whereIn('id', $tradeIds ?: [0])
            ->where('status', InvestmentTrade::STATUS_PENDING)
            ->exists();

        $pendingCost = InvestmentApprovedCost::query()
            ->whereIn('id', $costIds ?: [0])
            ->where('status', InvestmentApprovedCost::STATUS_PENDING)
            ->exists();

        $pendingAdjustment = $this->adjustmentsForTargets(
            $investment,
            $tradeIds,
            $costIds,
            InvestmentFinancialAdjustment::STATUS_PENDING
        )->isNotEmpty();

        $pendingEvidence = InvestmentEvidence::query()
            ->where('investment_profit_period_id', $currentPeriod->getKey())
            ->where('status', InvestmentEvidence::STATUS_PENDING)
            ->exists();

        if ($pendingTrade || $pendingCost || $pendingAdjustment || $pendingEvidence) {
            throw new DomainException(
                'Pending trade, cost, adjustment or evidence verification exists before the early-termination cutoff. Complete Finance verification first.'
            );
        }

        $verifiedTrades = InvestmentTrade::query()
            ->whereIn('id', $tradeIds ?: [0])
            ->where('status', InvestmentTrade::STATUS_VERIFIED)
            ->get();

        foreach ($verifiedTrades as $trade) {
            $evidences = InvestmentEvidence::query()
                ->where('investment_trade_id', $trade->getKey())
                ->where('status', InvestmentEvidence::STATUS_VERIFIED)
                ->get();

            if ($evidences->isEmpty()) {
                throw new DomainException(
                    'Every verified trade in the early-termination window must retain Finance-verified evidence.'
                );
            }

            foreach ($evidences as $evidence) {
                $this->assertEvidenceIntegrity($evidence);
            }
        }

        $pnlEvidence = InvestmentEvidence::query()
            ->where(
                'investment_settlement_id',
                $settlement->getKey()
            )
            ->where(
                'evidence_type',
                InvestmentEvidence::TYPE_TERMINAL_PNL_STATEMENT
            )
            ->where(
                'status',
                InvestmentEvidence::STATUS_VERIFIED
            )
            ->orderBy('id')
            ->get();

        if ($pnlEvidence->isEmpty()) {
            throw new DomainException(
                'A Finance-verified settlement-specific P&L statement/screenshot is required for the exact early-termination cutoff window.'
            );
        }

        foreach ($pnlEvidence as $evidence) {
            if (
                ! filled($evidence->account_reference)
                || ! filled($evidence->source_reference)
                || ! $evidence->period_start
                || ! $evidence->period_end
                || $evidence->period_start->toDateString()
                    !== $windowStart->toDateString()
                || $evidence->period_end->toDateString()
                    !== $cutoff->toDateString()
            ) {
                throw new DomainException(
                    'Terminal P&L evidence metadata does not match the exact cutoff window/account/reference requirements.'
                );
            }

            $this->assertEvidenceIntegrity($evidence);
        }
    }

    private function assertPriorPeriodsFinalized(
        Investment $investment,
        CarbonImmutable $cutoff
    ): void {
        $periods = $investment->profitPeriods()
            ->whereDate('period_end', '<=', $cutoff->toDateString())
            ->orderBy('sequence')
            ->get();

        foreach ($periods as $period) {
            if (! in_array($period->status, $this->finalPeriodStatuses(), true)) {
                throw new DomainException(
                    'Every profit-review period ending on or before the early-termination cutoff must be finalized before settlement preparation.'
                );
            }
        }
    }

    private function assertAllPeriodsSettled(Investment $investment): void
    {
        $periods = $investment->profitPeriods()
            ->orderBy('sequence')
            ->get();

        if ($periods->isEmpty()) {
            throw new DomainException(
                'Maturity settlement requires the generated profit-review schedule.'
            );
        }

        foreach ($periods as $period) {
            if (! in_array($period->status, $this->finalPeriodStatuses(), true)) {
                throw new DomainException(
                    'All profit-review periods must be fully finalized before maturity settlement preparation.'
                );
            }
        }
    }

    private function finalPeriodStatuses(): array
    {
        return [
            InvestmentProfitPeriod::STATUS_SETTLED,
            InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
            InvestmentProfitPeriod::STATUS_LOSS_CARRIED_FORWARD,
            InvestmentProfitPeriod::STATUS_PAID,
            InvestmentProfitPeriod::STATUS_NO_PROFIT,
        ];
    }

    private function adjustmentsForTargets(
        Investment $investment,
        array $tradeIds,
        array $costIds,
        string $status
    ): Collection {
        return InvestmentFinancialAdjustment::query()
            ->where('investment_id', $investment->getKey())
            ->where('status', $status)
            ->where(function ($query) use ($tradeIds, $costIds): void {
                $query
                    ->where(function ($tradeQuery) use ($tradeIds): void {
                        $tradeQuery
                            ->where('target_type', InvestmentFinancialAdjustment::TARGET_TRADE)
                            ->whereIn('target_id', $tradeIds ?: [0]);
                    })
                    ->orWhere(function ($costQuery) use ($costIds): void {
                        $costQuery
                            ->where('target_type', InvestmentFinancialAdjustment::TARGET_COST)
                            ->whereIn('target_id', $costIds ?: [0]);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    private function referenceCapitalAt(
        Investment $investment,
        CarbonImmutable $cutoff
    ): string {
        $topUps = InvestmentTransaction::query()
            ->where('investment_id', $investment->getKey())
            ->where('type', InvestmentTransaction::TYPE_TOP_UP)
            ->where('status', InvestmentTransaction::STATUS_VERIFIED)
            ->where('occurred_at', '<=', $cutoff)
            ->orderBy('id')
            ->pluck('usdt_equivalent')
            ->map(fn ($value) => (string) $value)
            ->all();

        return FixedDecimal::add(
            (string) $investment->deposit_amount,
            FixedDecimal::sum($topUps)
        );
    }

    private function emptySnapshot(
        CarbonImmutable $windowStart,
        CarbonImmutable $cutoff,
        string $openingLoss,
        string $referenceCapital
    ): array {
        return [
            'window_start' => $windowStart->toIso8601String(),
            'window_end' => $cutoff->toIso8601String(),
            'profit_period_id' => null,
            'reference_capital_usdt' => $referenceCapital,
            'gross_realized_profit_usdt' => '0.00000000',
            'realized_losses_usdt' => '0.00000000',
            'approved_costs_usdt' => '0.00000000',
            'loss_carryforward_open_usdt' => $openingLoss,
            'loss_carryforward_close_usdt' => $openingLoss,
            'eligible_net_profit_usdt' => '0.00000000',
            'customer_profit_share_usdt' => '0.00000000',
            'company_profit_share_usdt' => '0.00000000',
            'rounding_adjustment_usdt' => '0.00000000',
            'source' => [
                'trades' => [],
                'costs' => [],
                'adjustments' => [],
                'pnl_evidence' => [],
            ],
        ];
    }

    private function assertEvidenceIntegrity(InvestmentEvidence $evidence): void
    {
        if (
            ! $evidence->document_disk
            || ! $evidence->document_path
            || ! $evidence->document_sha256
            || ! Storage::disk($evidence->document_disk)
                ->exists($evidence->document_path)
        ) {
            throw new DomainException('Verified investment evidence is unavailable.');
        }

        $bytes = Storage::disk($evidence->document_disk)
            ->get($evidence->document_path);

        if (
            ! is_string($bytes)
            || ! hash_equals(
                $evidence->document_sha256,
                hash('sha256', $bytes)
            )
        ) {
            throw new DomainException(
                'Verified investment evidence integrity mismatch detected. Terminal settlement preparation is blocked.'
            );
        }
    }
}
