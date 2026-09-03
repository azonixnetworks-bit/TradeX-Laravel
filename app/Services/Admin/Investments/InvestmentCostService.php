<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Support\FixedDecimal;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvestmentCostService
{
    public function __construct(
        private readonly InvestmentPeriodResolver $periodResolver
    ) {
    }

    public function record(
        Investment $investment,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentApprovedCost {
        abort_unless($admin->hasPermission('investments.costs.record'), 403);

        if ($investment->status !== Investment::STATUS_ACTIVE) {
            throw new DomainException('Investment costs can be recorded only for an active investment.');
        }

        if (! in_array($data['cost_type'], InvestmentApprovedCost::allowedTypes(), true)) {
            throw new DomainException('This cost category is not an approved deductible investment cost.');
        }

        $period = $this->periodResolver->forTimestamp(
            $investment,
            (string) $data['occurred_at']
        );

        if ($period->approved_at !== null) {
            throw new DomainException(
                'An approved/finalized profit period is immutable. Record the correction in an open period instead.'
            );
        }

        $cost = InvestmentApprovedCost::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'investment_trade_id' => null,
            'cost_type' => $data['cost_type'],
            'amount_usdt' => FixedDecimal::normalize((string) $data['amount_usdt']),
            'occurred_at' => $data['occurred_at'],
            'source_reference' => trim((string) $data['source_reference']),
            'notes' => $data['notes'] ?? null,
            'status' => InvestmentApprovedCost::STATUS_PENDING,
            'recorded_by_admin_id' => $admin->getKey(),
        ]);

        $this->audit($admin, $request, 'INVESTMENT_COST_RECORDED', $cost, []);

        return $cost;
    }

    public function verify(
        Investment $investment,
        InvestmentApprovedCost $cost,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.costs.verify'), 403);
        $this->assertCost($investment, $cost);

        if ($cost->status !== InvestmentApprovedCost::STATUS_PENDING) {
            throw new DomainException('Only a pending investment cost can be verified.');
        }

        if (! filled($cost->source_reference)) {
            throw new DomainException('A broker/exchange/source reference is mandatory before cost verification.');
        }

        $cost->forceFill([
            'status' => InvestmentApprovedCost::STATUS_VERIFIED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $this->audit($admin, $request, 'INVESTMENT_COST_VERIFIED', $cost, []);
    }

    public function reject(
        Investment $investment,
        InvestmentApprovedCost $cost,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.costs.verify'), 403);
        $this->assertCost($investment, $cost);

        if ($cost->status !== InvestmentApprovedCost::STATUS_PENDING) {
            throw new DomainException('Only a pending investment cost can be rejected.');
        }

        $cost->forceFill([
            'status' => InvestmentApprovedCost::STATUS_REJECTED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->audit($admin, $request, 'INVESTMENT_COST_REJECTED', $cost, [
            'reason' => trim($reason),
        ]);
    }

    private function assertCost(Investment $investment, InvestmentApprovedCost $cost): void
    {
        abort_unless($cost->investment_id === $investment->getKey(), 404);
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentApprovedCost $cost,
        array $extra
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_APPROVED_COST',
            targetId: $cost->getKey(),
            newValues: array_merge([
                'investment_id' => $cost->investment_id,
                'profit_period_id' => $cost->investment_profit_period_id,
                'cost_type' => $cost->cost_type,
                'amount_usdt' => $cost->amount_usdt,
                'status' => $cost->status,
            ], $extra),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
