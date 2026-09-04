<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentAssetAllocation;
use App\Models\InvestmentAssetMovement;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentSettlement;
use App\Models\InvestmentTrade;
use App\Support\FixedDecimal;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentAdjustmentService
{
    public function record(
        Investment $investment,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentFinancialAdjustment {
        abort_unless($admin->hasPermission('investments.adjustments.record'), 403);
        $this->assertTerminalSettlementMutable($investment);

        $targetType = strtoupper((string) $data['target_type']);
        $targetId = (int) $data['target_id'];
        $type = strtoupper((string) $data['adjustment_type']);

        [$periodId, $pnlDelta, $costDelta] = $this->resolveDeltas(
            $investment,
            $targetType,
            $targetId,
            $type,
            $data
        );

        if ($type === InvestmentFinancialAdjustment::TYPE_REVERSAL) {
            $duplicate = InvestmentFinancialAdjustment::query()
                ->where('target_type', $targetType)
                ->where('target_id', $targetId)
                ->where('adjustment_type', InvestmentFinancialAdjustment::TYPE_REVERSAL)
                ->whereIn('status', [
                    InvestmentFinancialAdjustment::STATUS_PENDING,
                    InvestmentFinancialAdjustment::STATUS_VERIFIED,
                ])
                ->exists();

            if ($duplicate) {
                throw new DomainException('A reversal already exists or is pending for this verified record.');
            }
        }

        $adjustment = InvestmentFinancialAdjustment::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $periodId,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'adjustment_type' => $type,
            'realized_pnl_delta_usdt' => $pnlDelta,
            'approved_cost_delta_usdt' => $costDelta,
            'reason' => trim((string) $data['reason']),
            'status' => InvestmentFinancialAdjustment::STATUS_PENDING,
            'recorded_by_admin_id' => $admin->getKey(),
        ]);

        $this->audit($admin, $request, 'INVESTMENT_FINANCIAL_ADJUSTMENT_RECORDED', $adjustment, []);

        return $adjustment;
    }

    public function verify(
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.adjustments.verify'), 403);
        $this->assertAdjustment($investment, $adjustment);
        $this->assertTerminalSettlementMutable($investment);

        DB::transaction(function () use ($investment, $adjustment, $admin, $request): void {
            $locked = InvestmentFinancialAdjustment::query()
                ->lockForUpdate()
                ->findOrFail($adjustment->getKey());

            if ($locked->status !== InvestmentFinancialAdjustment::STATUS_PENDING) {
                throw new DomainException('Only a pending financial adjustment can be verified.');
            }

            if (
                $locked->adjustment_type === InvestmentFinancialAdjustment::TYPE_REVERSAL
                && $locked->target_type === InvestmentFinancialAdjustment::TARGET_TRADE
            ) {
                $this->reverseAssetMovements($investment, $locked, $admin);
            }

            $locked->forceFill([
                'status' => InvestmentFinancialAdjustment::STATUS_VERIFIED,
                'verified_by_admin_id' => $admin->getKey(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $this->audit($admin, $request, 'INVESTMENT_FINANCIAL_ADJUSTMENT_VERIFIED', $locked, []);
        }, 3);
    }

    public function reject(
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.adjustments.verify'), 403);
        $this->assertAdjustment($investment, $adjustment);

        if ($adjustment->status !== InvestmentFinancialAdjustment::STATUS_PENDING) {
            throw new DomainException('Only a pending financial adjustment can be rejected.');
        }

        $adjustment->forceFill([
            'status' => InvestmentFinancialAdjustment::STATUS_REJECTED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->audit($admin, $request, 'INVESTMENT_FINANCIAL_ADJUSTMENT_REJECTED', $adjustment, [
            'reason' => trim($reason),
        ]);
    }

    private function resolveDeltas(
        Investment $investment,
        string $targetType,
        int $targetId,
        string $type,
        array $data
    ): array {
        if ($targetType === InvestmentFinancialAdjustment::TARGET_TRADE) {
            $trade = InvestmentTrade::query()
                ->where('investment_id', $investment->getKey())
                ->findOrFail($targetId);

            if ($trade->status !== InvestmentTrade::STATUS_VERIFIED) {
                throw new DomainException('Only a verified trade can be corrected by reversal/adjustment.');
            }

            $this->assertTargetPeriodOpen((int) $trade->investment_profit_period_id);

            if ($type === InvestmentFinancialAdjustment::TYPE_REVERSAL) {
                $fee = FixedDecimal::sum(
                    InvestmentApprovedCost::query()
                        ->where('investment_trade_id', $trade->getKey())
                        ->where('status', InvestmentApprovedCost::STATUS_VERIFIED)
                        ->orderBy('id')
                        ->pluck('amount_usdt')
                        ->map(fn ($value) => (string) $value)
                        ->all()
                );

                return [
                    $trade->investment_profit_period_id,
                    FixedDecimal::negate((string) $trade->realized_pnl_usdt),
                    FixedDecimal::negate($fee),
                ];
            }

            $pnlDelta = FixedDecimal::normalize(
                (string) ($data['realized_pnl_delta_usdt'] ?? '0')
            );
            $costDelta = FixedDecimal::normalize(
                (string) ($data['approved_cost_delta_usdt'] ?? '0')
            );

            if (FixedDecimal::isZero($pnlDelta) && FixedDecimal::isZero($costDelta)) {
                throw new DomainException(
                    'An ADJUSTMENT must contain a non-zero realized P&L or approved-cost delta.'
                );
            }

            return [
                $trade->investment_profit_period_id,
                $pnlDelta,
                $costDelta,
            ];
        }

        if ($targetType === InvestmentFinancialAdjustment::TARGET_COST) {
            $cost = InvestmentApprovedCost::query()
                ->where('investment_id', $investment->getKey())
                ->findOrFail($targetId);

            if ($cost->status !== InvestmentApprovedCost::STATUS_VERIFIED) {
                throw new DomainException('Only a verified cost can be corrected by reversal/adjustment.');
            }

            $this->assertTargetPeriodOpen((int) $cost->investment_profit_period_id);

            return [
                $cost->investment_profit_period_id,
                FixedDecimal::normalize('0'),
                $type === InvestmentFinancialAdjustment::TYPE_REVERSAL
                    ? FixedDecimal::negate((string) $cost->amount_usdt)
                    : FixedDecimal::normalize((string) ($data['approved_cost_delta_usdt'] ?? '0')),
            ];
        }

        throw new DomainException('Unsupported financial adjustment target.');
    }

    private function assertTerminalSettlementMutable(Investment $investment): void
    {
        if (
            InvestmentSettlement::query()
                ->where('investment_id', $investment->getKey())
                ->whereNotNull('approved_at')
                ->exists()
        ) {
            throw new DomainException(
                'The terminal settlement has Finance approval. Financial adjustments are now immutable.'
            );
        }
    }

    private function assertTargetPeriodOpen(int $periodId): void
    {
        $period = InvestmentProfitPeriod::query()->findOrFail($periodId);

        if ($period->approved_at !== null) {
            throw new DomainException(
                'The original financial period is already approved/finalized and is immutable. Post-approval corrections require a later open-period correction workflow.'
            );
        }
    }

    private function reverseAssetMovements(
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment,
        AdminUser $admin
    ): void {
        $trade = InvestmentTrade::query()->findOrFail($adjustment->target_id);
        $movements = InvestmentAssetMovement::query()
            ->where('investment_trade_id', $trade->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($movements as $movement) {
            $allocation = InvestmentAssetAllocation::query()
                ->lockForUpdate()
                ->findOrFail($movement->investment_asset_allocation_id);

            $opposite = FixedDecimal::negate((string) $movement->units_delta, 18);
            $newRemaining = FixedDecimal::add(
                (string) $allocation->remaining_units,
                $opposite,
                18
            );

            if (
                FixedDecimal::isNegative($newRemaining, 18)
                || FixedDecimal::compare($newRemaining, (string) $allocation->original_units, 18) > 0
            ) {
                throw new DomainException(
                    'This trade cannot be reversed because later asset movements depend on its attributable units. Reverse dependent trades first.'
                );
            }

            $allocation->forceFill([
                'remaining_units' => $newRemaining,
                'status' => FixedDecimal::isZero($newRemaining, 18)
                    ? InvestmentAssetAllocation::STATUS_DEPLETED
                    : InvestmentAssetAllocation::STATUS_ACTIVE,
            ])->save();

            InvestmentAssetMovement::query()->create([
                'investment_id' => $investment->getKey(),
                'investment_trade_id' => null,
                'investment_financial_adjustment_id' => $adjustment->getKey(),
                'investment_asset_allocation_id' => $allocation->getKey(),
                'movement_type' => InvestmentAssetMovement::TYPE_REVERSAL,
                'units_delta' => $opposite,
                'created_by_admin_id' => $admin->getKey(),
            ]);
        }
    }

    private function assertAdjustment(
        Investment $investment,
        InvestmentFinancialAdjustment $adjustment
    ): void {
        abort_unless($adjustment->investment_id === $investment->getKey(), 404);
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentFinancialAdjustment $adjustment,
        array $extra
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_FINANCIAL_ADJUSTMENT',
            targetId: $adjustment->getKey(),
            newValues: array_merge([
                'investment_id' => $adjustment->investment_id,
                'profit_period_id' => $adjustment->investment_profit_period_id,
                'target_type' => $adjustment->target_type,
                'target_id' => $adjustment->target_id,
                'adjustment_type' => $adjustment->adjustment_type,
                'realized_pnl_delta_usdt' => $adjustment->realized_pnl_delta_usdt,
                'approved_cost_delta_usdt' => $adjustment->approved_cost_delta_usdt,
                'status' => $adjustment->status,
            ], $extra),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
