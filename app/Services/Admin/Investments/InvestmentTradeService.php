<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentAssetAllocation;
use App\Models\InvestmentAssetMovement;
use App\Models\InvestmentEvidence;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentTrade;
use App\Support\FixedDecimal;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvestmentTradeService
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
    ): InvestmentTrade {
        abort_unless($admin->hasPermission('investments.trades.record'), 403);

        if ($investment->status !== Investment::STATUS_ACTIVE) {
            throw new DomainException('Spot trades can be recorded only for an active investment.');
        }

        $period = $this->periodResolver->forTimestamp(
            $investment,
            (string) $data['executed_at']
        );

        if ($period->approved_at !== null) {
            throw new DomainException(
                'An approved/finalized profit period is immutable. Record the correction in an open period instead.'
            );
        }

        $action = strtoupper((string) $data['action']);
        $realizedPnl = $action === InvestmentTrade::ACTION_SELL
            ? FixedDecimal::normalize((string) ($data['realized_pnl_usdt'] ?? '0'))
            : FixedDecimal::normalize('0');

        $trade = InvestmentTrade::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_id' => $investment->getKey(),
            'investment_profit_period_id' => $period->getKey(),
            'action' => $action,
            'instrument_type' => InvestmentTrade::INSTRUMENT_SPOT,
            'asset_name' => trim((string) $data['asset_name']),
            'asset_symbol' => strtoupper(trim((string) $data['asset_symbol'])),
            'network' => filled($data['network'] ?? null)
                ? trim((string) $data['network'])
                : null,
            'contract_address' => filled($data['contract_address'] ?? null)
                ? trim((string) $data['contract_address'])
                : null,
            'exchange_broker' => trim((string) $data['exchange_broker']),
            'quantity' => FixedDecimal::normalize((string) $data['quantity'], 18),
            'executed_price_usdt' => FixedDecimal::normalize((string) $data['executed_price_usdt']),
            'gross_value_usdt' => FixedDecimal::normalize((string) $data['gross_value_usdt']),
            'fee_usdt' => FixedDecimal::normalize((string) ($data['fee_usdt'] ?? '0')),
            'realized_pnl_usdt' => $realizedPnl,
            'order_reference' => filled($data['order_reference'] ?? null)
                ? trim((string) $data['order_reference'])
                : null,
            'executed_at' => $data['executed_at'],
            'status' => InvestmentTrade::STATUS_PENDING,
            'recorded_by_admin_id' => $admin->getKey(),
        ]);

        $this->audit($admin, $request, 'INVESTMENT_SPOT_TRADE_RECORDED', $trade, [
            'action' => $trade->action,
            'asset_symbol' => $trade->asset_symbol,
            'quantity' => $trade->quantity,
            'realized_pnl_usdt' => $trade->realized_pnl_usdt,
        ]);

        return $trade;
    }

    public function verify(
        Investment $investment,
        InvestmentTrade $trade,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.trades.verify'), 403);
        $this->assertTrade($investment, $trade);

        DB::transaction(function () use ($investment, $trade, $admin, $request): void {
            $lockedTrade = InvestmentTrade::query()
                ->lockForUpdate()
                ->findOrFail($trade->getKey());

            if ($lockedTrade->status !== InvestmentTrade::STATUS_PENDING) {
                throw new DomainException('Only a pending spot trade can be verified.');
            }

            $verifiedEvidences = InvestmentEvidence::query()
                ->where('investment_trade_id', $lockedTrade->getKey())
                ->where('status', InvestmentEvidence::STATUS_VERIFIED)
                ->orderBy('id')
                ->get();

            if ($verifiedEvidences->isEmpty()) {
                throw new DomainException(
                    'Finance must verify at least one screenshot/image or document evidence before the trade can enter the official ledger.'
                );
            }

            foreach ($verifiedEvidences as $evidence) {
                $this->assertEvidenceIntegrity($evidence);
            }

            if ($lockedTrade->instrument_type !== InvestmentTrade::INSTRUMENT_SPOT) {
                throw new DomainException('Investment V1 permits spot crypto trades only.');
            }

            if ($lockedTrade->action === InvestmentTrade::ACTION_BUY) {
                $this->applyBuy($investment, $lockedTrade, $admin);
            } elseif ($lockedTrade->action === InvestmentTrade::ACTION_SELL) {
                $this->applySell($investment, $lockedTrade, $admin);
            } else {
                throw new DomainException('Unsupported spot trade action.');
            }

            $lockedTrade->forceFill([
                'status' => InvestmentTrade::STATUS_VERIFIED,
                'verified_by_admin_id' => $admin->getKey(),
                'verified_at' => now(),
                'rejection_reason' => null,
            ])->save();

            if (FixedDecimal::isPositive((string) $lockedTrade->fee_usdt)) {
                InvestmentApprovedCost::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'investment_id' => $investment->getKey(),
                    'investment_profit_period_id' => $lockedTrade->investment_profit_period_id,
                    'investment_trade_id' => $lockedTrade->getKey(),
                    'cost_type' => InvestmentApprovedCost::TYPE_EXCHANGE_TRADING_FEE,
                    'amount_usdt' => FixedDecimal::normalize((string) $lockedTrade->fee_usdt),
                    'occurred_at' => $lockedTrade->executed_at,
                    'source_reference' => $lockedTrade->order_reference
                        ?: 'VERIFIED-TRADE-'.$lockedTrade->uuid,
                    'notes' => 'Auto-created from Finance-verified trade evidence.',
                    'status' => InvestmentApprovedCost::STATUS_VERIFIED,
                    'recorded_by_admin_id' => $lockedTrade->recorded_by_admin_id,
                    'verified_by_admin_id' => $admin->getKey(),
                    'verified_at' => now(),
                ]);
            }

            $this->audit($admin, $request, 'INVESTMENT_SPOT_TRADE_VERIFIED', $lockedTrade, [
                'status' => InvestmentTrade::STATUS_VERIFIED,
            ]);
        }, 3);
    }

    public function reject(
        Investment $investment,
        InvestmentTrade $trade,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.trades.verify'), 403);
        $this->assertTrade($investment, $trade);

        if ($trade->status !== InvestmentTrade::STATUS_PENDING) {
            throw new DomainException('Only a pending spot trade can be rejected.');
        }

        $trade->forceFill([
            'status' => InvestmentTrade::STATUS_REJECTED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'rejection_reason' => trim($reason),
        ])->save();

        $this->audit($admin, $request, 'INVESTMENT_SPOT_TRADE_REJECTED', $trade, [
            'reason' => trim($reason),
        ]);
    }

    private function applyBuy(
        Investment $investment,
        InvestmentTrade $trade,
        AdminUser $admin
    ): void {
        $allocation = InvestmentAssetAllocation::query()->create([
            'investment_id' => $investment->getKey(),
            'source_transaction_id' => null,
            'source_trade_id' => $trade->getKey(),
            'asset_symbol' => $trade->asset_symbol,
            'network' => $trade->network,
            'contract_address' => $trade->contract_address,
            'original_units' => FixedDecimal::normalize((string) $trade->quantity, 18),
            'remaining_units' => FixedDecimal::normalize((string) $trade->quantity, 18),
            'allocated_cost_usdt' => FixedDecimal::add(
                (string) $trade->gross_value_usdt,
                (string) $trade->fee_usdt
            ),
            'acquired_at' => $trade->executed_at,
            'status' => InvestmentAssetAllocation::STATUS_ACTIVE,
        ]);

        InvestmentAssetMovement::query()->create([
            'investment_id' => $investment->getKey(),
            'investment_trade_id' => $trade->getKey(),
            'investment_financial_adjustment_id' => null,
            'investment_asset_allocation_id' => $allocation->getKey(),
            'movement_type' => InvestmentAssetMovement::TYPE_BUY,
            'units_delta' => FixedDecimal::normalize((string) $trade->quantity, 18),
            'created_by_admin_id' => $admin->getKey(),
        ]);
    }

    private function applySell(
        Investment $investment,
        InvestmentTrade $trade,
        AdminUser $admin
    ): void {
        $query = InvestmentAssetAllocation::query()
            ->where('investment_id', $investment->getKey())
            ->where('asset_symbol', $trade->asset_symbol)
            ->where('status', InvestmentAssetAllocation::STATUS_ACTIVE)
            ->where('remaining_units', '>', 0)
            ->orderBy('acquired_at')
            ->orderBy('id')
            ->lockForUpdate();

        if ($trade->network !== null) {
            $query->where('network', $trade->network);
        } else {
            $query->whereNull('network');
        }

        if ($trade->contract_address !== null) {
            $query->where('contract_address', $trade->contract_address);
        } else {
            $query->whereNull('contract_address');
        }

        $allocations = $query->get();
        $available = FixedDecimal::sum(
            $allocations->pluck('remaining_units')->all(),
            18
        );
        $required = FixedDecimal::normalize((string) $trade->quantity, 18);

        if (FixedDecimal::compare($available, $required, 18) < 0) {
            throw new DomainException(
                'The verified customer asset ledger does not contain enough attributable units for this spot sell.'
            );
        }

        $remainingToSell = $required;

        foreach ($allocations as $allocation) {
            if (FixedDecimal::isZero($remainingToSell, 18)) {
                break;
            }

            $take = FixedDecimal::min(
                (string) $allocation->remaining_units,
                $remainingToSell,
                18
            );

            $newRemaining = FixedDecimal::sub(
                (string) $allocation->remaining_units,
                $take,
                18
            );

            $allocation->forceFill([
                'remaining_units' => $newRemaining,
                'status' => FixedDecimal::isZero($newRemaining, 18)
                    ? InvestmentAssetAllocation::STATUS_DEPLETED
                    : InvestmentAssetAllocation::STATUS_ACTIVE,
            ])->save();

            InvestmentAssetMovement::query()->create([
                'investment_id' => $investment->getKey(),
                'investment_trade_id' => $trade->getKey(),
                'investment_financial_adjustment_id' => null,
                'investment_asset_allocation_id' => $allocation->getKey(),
                'movement_type' => InvestmentAssetMovement::TYPE_SELL,
                'units_delta' => FixedDecimal::negate($take, 18),
                'created_by_admin_id' => $admin->getKey(),
            ]);

            $remainingToSell = FixedDecimal::sub($remainingToSell, $take, 18);
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
                'Verified investment evidence integrity mismatch detected. Finance verification is blocked.'
            );
        }
    }

    private function assertTrade(Investment $investment, InvestmentTrade $trade): void
    {
        abort_unless($trade->investment_id === $investment->getKey(), 404);
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentTrade $trade,
        array $values
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_TRADE',
            targetId: $trade->getKey(),
            newValues: array_merge([
                'investment_id' => $trade->investment_id,
                'profit_period_id' => $trade->investment_profit_period_id,
                'trade_uuid' => $trade->uuid,
                'instrument_type' => $trade->instrument_type,
            ], $values),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
