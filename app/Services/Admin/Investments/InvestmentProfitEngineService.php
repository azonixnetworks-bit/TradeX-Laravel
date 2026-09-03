<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTrade;
use App\Models\InvestmentTransaction;
use App\Support\FixedDecimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvestmentProfitEngineService
{
    public const CALCULATION_VERSION = 'PERFORMANCE-SHARE-v1';

    public function prepare(
        Investment $investment,
        InvestmentProfitPeriod $period,
        AdminUser $admin,
        Request $request
    ): InvestmentProfitPeriod {
        abort_unless($admin->hasPermission('investments.profit_periods.prepare'), 403);
        $this->assertPeriod($investment, $period);

        if (! in_array($investment->status, [Investment::STATUS_ACTIVE, Investment::STATUS_MATURED], true)) {
            throw new DomainException('Profit periods can be prepared only for active or matured investments.');
        }

        if (CarbonImmutable::parse($period->period_end)->isFuture()) {
            throw new DomainException('This profit-review period is not due yet.');
        }

        if ($period->approved_at !== null || in_array($period->status, [
            InvestmentProfitPeriod::STATUS_APPROVED,
            InvestmentProfitPeriod::STATUS_PAYMENT_RECORDED,
            InvestmentProfitPeriod::STATUS_SETTLED,
            InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
            InvestmentProfitPeriod::STATUS_LOSS_CARRIED_FORWARD,
        ], true)) {
            throw new DomainException('An approved/finalized profit period is immutable.');
        }

        $previous = null;
        if ($period->sequence > 1) {
            $previous = $investment->profitPeriods()
                ->where('sequence', $period->sequence - 1)
                ->firstOrFail();

            if ($previous->approved_at === null) {
                throw new DomainException('The previous profit-review period must receive Finance approval first.');
            }
        }

        $this->assertLedgerReady($investment, $period);

        return DB::transaction(function () use (
            $investment,
            $period,
            $previous,
            $admin,
            $request
        ): InvestmentProfitPeriod {
            $locked = InvestmentProfitPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->getKey());

            if ($locked->approved_at !== null || in_array($locked->status, [
                InvestmentProfitPeriod::STATUS_APPROVED,
                InvestmentProfitPeriod::STATUS_PAYMENT_RECORDED,
                InvestmentProfitPeriod::STATUS_SETTLED,
                InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
                InvestmentProfitPeriod::STATUS_LOSS_CARRIED_FORWARD,
            ], true)) {
                throw new DomainException('An approved/finalized profit period is immutable.');
            }

            $sourceHash = $this->sourceHash($investment, $locked);
            $numbers = $this->calculate($investment, $locked, $previous);

            $snapshot = [
                'calculation_version' => self::CALCULATION_VERSION,
                'basis' => 'CLOSED_REALIZED_ONLY',
                'floating_pnl_included' => false,
                'approved_cost_types' => InvestmentApprovedCost::allowedTypes(),
                'customer_profit_share_bps' => $investment->customer_profit_share_bps,
                'company_profit_share_bps' => $investment->company_profit_share_bps,
                'rounding' => 'HALF_UP_8DP',
                'source_sha256' => $sourceHash,
                'prepared_at' => now()->toIso8601String(),
                'prepared_by_admin_id' => $admin->getKey(),
                'numbers' => $numbers,
            ];

            $locked->forceFill(array_merge($numbers, [
                'status' => InvestmentProfitPeriod::STATUS_APPROVAL_PENDING,
                'calculation_version' => self::CALCULATION_VERSION,
                'calculation_source_sha256' => $sourceHash,
                'calculation_snapshot' => $snapshot,
                'prepared_by_admin_id' => $admin->getKey(),
                'prepared_at' => now(),
                'approved_by_admin_id' => null,
                'approved_at' => null,
            ]))->save();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'INVESTMENT_PROFIT_PERIOD_PREPARED',
                module: 'INVESTMENTS',
                targetType: 'INVESTMENT_PROFIT_PERIOD',
                targetId: $locked->getKey(),
                newValues: [
                    'investment_id' => $investment->getKey(),
                    'sequence' => $locked->sequence,
                    'distribution_outcome' => $locked->distribution_outcome,
                    'eligible_net_profit_usdt' => $locked->eligible_net_profit_usdt,
                    'customer_profit_share_usdt' => $locked->customer_profit_share_usdt,
                    'company_profit_share_usdt' => $locked->company_profit_share_usdt,
                    'loss_carryforward_close_usdt' => $locked->loss_carryforward_close_usdt,
                    'source_sha256' => $sourceHash,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $locked->fresh();
        }, 3);
    }

    public function approve(
        Investment $investment,
        InvestmentProfitPeriod $period,
        AdminUser $admin,
        Request $request
    ): InvestmentProfitPeriod {
        abort_unless($admin->hasPermission('investments.profit_periods.approve'), 403);
        $this->assertPeriod($investment, $period);

        return DB::transaction(function () use ($investment, $period, $admin, $request): InvestmentProfitPeriod {
            $lockedInvestment = Investment::query()
                ->lockForUpdate()
                ->findOrFail($investment->getKey());
            $locked = InvestmentProfitPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->getKey());

            if ($locked->status !== InvestmentProfitPeriod::STATUS_APPROVAL_PENDING) {
                throw new DomainException('Only a prepared period awaiting approval can be approved.');
            }

            if (
                $locked->prepared_by_admin_id === $admin->getKey()
                && ! $admin->isSuperAdmin()
            ) {
                throw new DomainException(
                    'Maker-checker separation requires a different Finance Admin to approve this period.'
                );
            }

            if (! $locked->calculation_source_sha256) {
                throw new DomainException('The calculation source fingerprint is missing. Re-prepare the period.');
            }

            $this->assertLedgerReady($lockedInvestment, $locked);
            $freshHash = $this->sourceHash($lockedInvestment, $locked);

            if (! hash_equals($locked->calculation_source_sha256, $freshHash)) {
                throw new DomainException(
                    'Verified trade/cost/evidence data changed after preparation. Re-prepare the period before approval.'
                );
            }

            $outcome = $locked->distribution_outcome;
            $nextStatus = match ($outcome) {
                InvestmentProfitPeriod::OUTCOME_PAYABLE => InvestmentProfitPeriod::STATUS_APPROVED,
                InvestmentProfitPeriod::OUTCOME_NO_DISTRIBUTION => InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
                InvestmentProfitPeriod::OUTCOME_LOSS_CARRIED_FORWARD => InvestmentProfitPeriod::STATUS_LOSS_CARRIED_FORWARD,
                default => throw new DomainException('Profit period outcome is invalid.'),
            };

            $locked->forceFill([
                'status' => $nextStatus,
                'approved_by_admin_id' => $admin->getKey(),
                'approved_at' => now(),
            ])->save();

            $lockedInvestment->forceFill([
                'loss_carryforward_usdt' => FixedDecimal::normalize(
                    (string) $locked->loss_carryforward_close_usdt
                ),
                'updated_by_admin_id' => $admin->getKey(),
            ])->save();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'INVESTMENT_PROFIT_PERIOD_APPROVED',
                module: 'INVESTMENTS',
                targetType: 'INVESTMENT_PROFIT_PERIOD',
                targetId: $locked->getKey(),
                newValues: [
                    'investment_id' => $lockedInvestment->getKey(),
                    'sequence' => $locked->sequence,
                    'status' => $locked->status,
                    'distribution_outcome' => $outcome,
                    'loss_carryforward_close_usdt' => $locked->loss_carryforward_close_usdt,
                    'maker_admin_id' => $locked->prepared_by_admin_id,
                    'checker_admin_id' => $admin->getKey(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $locked->fresh();
        }, 3);
    }

    private function calculate(
        Investment $investment,
        InvestmentProfitPeriod $period,
        ?InvestmentProfitPeriod $previous
    ): array {
        $pnlValues = InvestmentTrade::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentTrade::STATUS_VERIFIED)
            ->orderBy('id')
            ->pluck('realized_pnl_usdt')
            ->map(fn ($value) => (string) $value)
            ->all();

        $adjustments = InvestmentFinancialAdjustment::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentFinancialAdjustment::STATUS_VERIFIED)
            ->orderBy('id')
            ->get();

        foreach ($adjustments as $adjustment) {
            if (! FixedDecimal::isZero((string) $adjustment->realized_pnl_delta_usdt)) {
                $pnlValues[] = (string) $adjustment->realized_pnl_delta_usdt;
            }
        }

        $grossProfit = FixedDecimal::normalize('0');
        $realizedLoss = FixedDecimal::normalize('0');

        foreach ($pnlValues as $pnl) {
            $normalized = FixedDecimal::normalize($pnl);
            if (FixedDecimal::isPositive($normalized)) {
                $grossProfit = FixedDecimal::add($grossProfit, $normalized);
            } elseif (FixedDecimal::isNegative($normalized)) {
                $realizedLoss = FixedDecimal::add(
                    $realizedLoss,
                    FixedDecimal::abs($normalized)
                );
            }
        }

        $costValues = InvestmentApprovedCost::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentApprovedCost::STATUS_VERIFIED)
            ->orderBy('id')
            ->pluck('amount_usdt')
            ->map(fn ($value) => (string) $value)
            ->all();

        $approvedCosts = FixedDecimal::sum($costValues);
        foreach ($adjustments as $adjustment) {
            $approvedCosts = FixedDecimal::add(
                $approvedCosts,
                (string) $adjustment->approved_cost_delta_usdt
            );
        }

        if (FixedDecimal::isNegative($approvedCosts)) {
            throw new DomainException('Verified cost adjustments cannot reduce approved costs below zero.');
        }

        $currentNet = FixedDecimal::sub(
            FixedDecimal::sub($grossProfit, $realizedLoss),
            $approvedCosts
        );

        $openingLoss = $previous
            ? FixedDecimal::normalize((string) $previous->loss_carryforward_close_usdt)
            : FixedDecimal::normalize((string) $investment->loss_carryforward_usdt);

        $eligible = FixedDecimal::normalize('0');
        $closingLoss = $openingLoss;
        $outcome = InvestmentProfitPeriod::OUTCOME_NO_DISTRIBUTION;

        if (FixedDecimal::isNegative($currentNet)) {
            $closingLoss = FixedDecimal::add($openingLoss, FixedDecimal::abs($currentNet));
            $outcome = InvestmentProfitPeriod::OUTCOME_LOSS_CARRIED_FORWARD;
        } elseif (FixedDecimal::isPositive($currentNet)) {
            $recovery = FixedDecimal::min($currentNet, $openingLoss);
            $closingLoss = FixedDecimal::sub($openingLoss, $recovery);
            $eligible = FixedDecimal::sub($currentNet, $recovery);

            if (FixedDecimal::isPositive($eligible)) {
                $outcome = InvestmentProfitPeriod::OUTCOME_PAYABLE;
            } elseif (FixedDecimal::isPositive($closingLoss)) {
                $outcome = InvestmentProfitPeriod::OUTCOME_LOSS_CARRIED_FORWARD;
            }
        } elseif (FixedDecimal::isPositive($openingLoss)) {
            $outcome = InvestmentProfitPeriod::OUTCOME_LOSS_CARRIED_FORWARD;
        }

        $customerIndependent = FixedDecimal::multiplyBasisPoints(
            $eligible,
            (int) $investment->customer_profit_share_bps
        );
        $companyIndependent = FixedDecimal::multiplyBasisPoints(
            $eligible,
            (int) $investment->company_profit_share_bps
        );
        $independentTotal = FixedDecimal::add($customerIndependent, $companyIndependent);
        $roundingAdjustment = FixedDecimal::sub($eligible, $independentTotal);
        $companyFinal = FixedDecimal::add($companyIndependent, $roundingAdjustment);

        $hwmOpen = $this->referenceCapitalAt($investment, $period, false);
        $hwmClose = $this->referenceCapitalAt($investment, $period, true);
        $closingEquity = FixedDecimal::add($hwmClose, $currentNet);

        return [
            'distribution_outcome' => $outcome,
            'opening_equity_usdt' => $hwmOpen,
            'closing_equity_usdt' => $closingEquity,
            'gross_realized_profit_usdt' => $grossProfit,
            'realized_losses_usdt' => $realizedLoss,
            'approved_costs_usdt' => $approvedCosts,
            'loss_carryforward_open_usdt' => $openingLoss,
            'loss_carryforward_close_usdt' => $closingLoss,
            'high_water_mark_open_usdt' => $hwmOpen,
            'high_water_mark_close_usdt' => $hwmClose,
            'eligible_net_profit_usdt' => $eligible,
            'customer_profit_share_usdt' => $customerIndependent,
            'company_profit_share_usdt' => $companyFinal,
            'rounding_adjustment_usdt' => $roundingAdjustment,
        ];
    }

    private function referenceCapitalAt(
        Investment $investment,
        InvestmentProfitPeriod $period,
        bool $includePeriodEnd
    ): string {
        $query = InvestmentTransaction::query()
            ->where('investment_id', $investment->getKey())
            ->where('type', InvestmentTransaction::TYPE_TOP_UP)
            ->where('status', InvestmentTransaction::STATUS_VERIFIED);

        if ($includePeriodEnd) {
            $query->whereDate('occurred_at', '<=', $period->period_end->toDateString());
        } else {
            $query->whereDate('occurred_at', '<', $period->period_start->toDateString());
        }

        $topUps = FixedDecimal::sum(
            $query->orderBy('id')
                ->pluck('usdt_equivalent')
                ->map(fn ($value) => (string) $value)
                ->all()
        );

        return FixedDecimal::add((string) $investment->deposit_amount, $topUps);
    }

    private function assertLedgerReady(
        Investment $investment,
        InvestmentProfitPeriod $period
    ): void {
        foreach ([
            [InvestmentTrade::class, 'investment_profit_period_id'],
            [InvestmentApprovedCost::class, 'investment_profit_period_id'],
            [InvestmentFinancialAdjustment::class, 'investment_profit_period_id'],
            [InvestmentEvidence::class, 'investment_profit_period_id'],
        ] as [$model, $column]) {
            $pending = $model::query()
                ->where($column, $period->getKey())
                ->where('status', 'PENDING_VERIFICATION')
                ->exists();

            if ($pending) {
                throw new DomainException(
                    'Pending trade, cost, adjustment or evidence verification exists for this period. Complete Finance verification first.'
                );
            }
        }

        $trades = InvestmentTrade::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentTrade::STATUS_VERIFIED)
            ->get();

        foreach ($trades as $trade) {
            $hasEvidence = InvestmentEvidence::query()
                ->where('investment_trade_id', $trade->getKey())
                ->where('status', InvestmentEvidence::STATUS_VERIFIED)
                ->exists();

            if (! $hasEvidence) {
                throw new DomainException('Every verified trade must retain verified screenshot/document evidence.');
            }
        }

        if ($trades->isEmpty()) {
            $periodEvidence = InvestmentEvidence::query()
                ->where('investment_profit_period_id', $period->getKey())
                ->whereNull('investment_trade_id')
                ->whereNull('investment_approved_cost_id')
                ->where('evidence_type', InvestmentEvidence::TYPE_PNL_STATEMENT)
                ->where('status', InvestmentEvidence::STATUS_VERIFIED)
                ->exists();

            if (! $periodEvidence) {
                throw new DomainException(
                    'A Finance-verified P&L statement/screenshot is required to finalize a no-trade period.'
                );
            }
        }

        $verifiedEvidence = InvestmentEvidence::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentEvidence::STATUS_VERIFIED)
            ->orderBy('id')
            ->get();

        foreach ($verifiedEvidence as $evidence) {
            $this->assertEvidenceIntegrity($evidence);
        }
    }

    private function assertEvidenceIntegrity(InvestmentEvidence $evidence): void
    {
        if (
            ! $evidence->document_disk
            || ! $evidence->document_path
            || ! $evidence->document_sha256
            || ! Storage::disk($evidence->document_disk)->exists($evidence->document_path)
        ) {
            throw new DomainException('Verified investment evidence is unavailable.');
        }

        $bytes = Storage::disk($evidence->document_disk)->get($evidence->document_path);

        if (
            ! is_string($bytes)
            || ! hash_equals($evidence->document_sha256, hash('sha256', $bytes))
        ) {
            throw new DomainException(
                'Verified investment evidence integrity mismatch detected. Profit approval is blocked.'
            );
        }
    }

    private function sourceHash(
        Investment $investment,
        InvestmentProfitPeriod $period
    ): string {
        $trades = InvestmentTrade::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentTrade::STATUS_VERIFIED)
            ->orderBy('id')
            ->get([
                'id', 'uuid', 'action', 'asset_symbol', 'quantity',
                'executed_price_usdt', 'gross_value_usdt', 'fee_usdt',
                'realized_pnl_usdt', 'executed_at', 'verified_at', 'updated_at',
            ])->toArray();

        $costs = InvestmentApprovedCost::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentApprovedCost::STATUS_VERIFIED)
            ->orderBy('id')
            ->get([
                'id', 'uuid', 'cost_type', 'amount_usdt', 'occurred_at',
                'source_reference', 'verified_at', 'updated_at',
            ])->toArray();

        $adjustments = InvestmentFinancialAdjustment::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentFinancialAdjustment::STATUS_VERIFIED)
            ->orderBy('id')
            ->get([
                'id', 'uuid', 'target_type', 'target_id', 'adjustment_type',
                'realized_pnl_delta_usdt', 'approved_cost_delta_usdt',
                'reason', 'verified_at', 'updated_at',
            ])->toArray();

        $evidence = InvestmentEvidence::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('status', InvestmentEvidence::STATUS_VERIFIED)
            ->orderBy('id')
            ->get([
                'id', 'uuid', 'evidence_type', 'investment_trade_id',
                'investment_approved_cost_id', 'document_sha256',
                'document_size_bytes', 'verified_at', 'updated_at',
            ])->toArray();

        $topUps = InvestmentTransaction::query()
            ->where('investment_id', $investment->getKey())
            ->where('type', InvestmentTransaction::TYPE_TOP_UP)
            ->where('status', InvestmentTransaction::STATUS_VERIFIED)
            ->whereDate('occurred_at', '<=', $period->period_end->toDateString())
            ->orderBy('id')
            ->get(['id', 'amount', 'usdt_equivalent', 'txid', 'occurred_at', 'verified_at'])
            ->toArray();

        $payload = json_encode([
            'investment_id' => $investment->getKey(),
            'period_id' => $period->getKey(),
            'trades' => $trades,
            'costs' => $costs,
            'adjustments' => $adjustments,
            'evidence' => $evidence,
            'top_ups' => $topUps,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return hash('sha256', $payload);
    }

    private function assertPeriod(Investment $investment, InvestmentProfitPeriod $period): void
    {
        abort_unless($period->investment_id === $investment->getKey(), 404);
    }
}
