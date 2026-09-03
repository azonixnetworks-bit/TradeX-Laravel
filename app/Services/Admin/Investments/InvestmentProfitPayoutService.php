<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentTransaction;
use App\Support\FixedDecimal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentProfitPayoutService
{
    public function record(
        Investment $investment,
        InvestmentProfitPeriod $period,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentTransaction {
        abort_unless($admin->hasPermission('investments.transactions.record'), 403);
        abort_unless($admin->hasPermission('investments.payouts.approve'), 403);
        $this->assertPeriod($investment, $period);

        if ($period->status !== InvestmentProfitPeriod::STATUS_APPROVED) {
            throw new DomainException('Only a Finance-approved payable period can record a profit payout.');
        }

        if ($period->distribution_outcome !== InvestmentProfitPeriod::OUTCOME_PAYABLE) {
            throw new DomainException('This period has no customer profit distribution to pay.');
        }

        if (! $investment->payout_wallet_verified_at || $investment->payout_wallet_network !== 'TRC20') {
            throw new DomainException('The customer USDT TRC20 payout wallet must be verified.');
        }

        $existing = InvestmentTransaction::query()
            ->where('investment_profit_period_id', $period->getKey())
            ->where('type', InvestmentTransaction::TYPE_PROFIT_PAYOUT)
            ->whereIn('status', [
                InvestmentTransaction::STATUS_PENDING,
                InvestmentTransaction::STATUS_VERIFIED,
            ])->exists();

        if ($existing) {
            throw new DomainException('A profit payout already exists for this period.');
        }

        $txid = strtolower(trim((string) $data['txid']));
        if (InvestmentTransaction::query()->where('network', 'TRC20')->where('txid', $txid)->exists()) {
            throw new DomainException('This TRC20 transaction ID is already recorded.');
        }

        $amount = FixedDecimal::normalize((string) $period->customer_profit_share_usdt);
        if (! FixedDecimal::isPositive($amount)) {
            throw new DomainException('Customer profit share must be positive before payout.');
        }

        try {
            return DB::transaction(function () use (
                $investment,
                $period,
                $data,
                $admin,
                $request,
                $txid,
                $amount
            ): InvestmentTransaction {
                $lockedPeriod = InvestmentProfitPeriod::query()
                    ->lockForUpdate()
                    ->findOrFail($period->getKey());

                if ($lockedPeriod->status !== InvestmentProfitPeriod::STATUS_APPROVED) {
                    throw new DomainException('Profit period state changed before payout recording.');
                }

                $transaction = InvestmentTransaction::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'investment_id' => $investment->getKey(),
                    'investment_profit_period_id' => $lockedPeriod->getKey(),
                    'direction' => InvestmentTransaction::DIRECTION_OUT,
                    'type' => InvestmentTransaction::TYPE_PROFIT_PAYOUT,
                    'asset_symbol' => 'USDT',
                    'amount' => $amount,
                    'usdt_equivalent' => $amount,
                    'network' => 'TRC20',
                    'from_address' => $data['from_address'] ?? null,
                    'to_address' => $investment->payout_wallet_address,
                    'txid' => $txid,
                    'status' => InvestmentTransaction::STATUS_PENDING,
                    'occurred_at' => $data['occurred_at'],
                    'recorded_by_admin_id' => $admin->getKey(),
                    'notes' => $data['notes'] ?? null,
                ]);

                $lockedPeriod->forceFill([
                    'status' => InvestmentProfitPeriod::STATUS_PAYMENT_RECORDED,
                ])->save();

                $this->audit($admin, $request, 'INVESTMENT_PROFIT_PAYOUT_RECORDED', $transaction, [
                    'period_status' => $lockedPeriod->status,
                ]);

                return $transaction;
            }, 3);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'investment_transactions_network_txid_unique')) {
                throw new DomainException('This TRC20 transaction ID is already recorded.');
            }
            throw $exception;
        }
    }

    public function verify(
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTransaction $transaction,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.transactions.verify'), 403);
        $this->assertPeriod($investment, $period);
        $this->assertPayout($investment, $period, $transaction);

        DB::transaction(function () use ($period, $transaction, $admin, $request): void {
            $lockedPeriod = InvestmentProfitPeriod::query()
                ->lockForUpdate()
                ->findOrFail($period->getKey());
            $lockedTransaction = InvestmentTransaction::query()
                ->lockForUpdate()
                ->findOrFail($transaction->getKey());

            if ($lockedPeriod->status !== InvestmentProfitPeriod::STATUS_PAYMENT_RECORDED) {
                throw new DomainException('Profit period is not waiting for payout verification.');
            }

            if ($lockedTransaction->status !== InvestmentTransaction::STATUS_PENDING) {
                throw new DomainException('Only a pending profit payout can be verified.');
            }

            $expected = FixedDecimal::normalize((string) $lockedPeriod->customer_profit_share_usdt);
            $actual = FixedDecimal::normalize((string) $lockedTransaction->usdt_equivalent);

            if (
                $lockedTransaction->asset_symbol !== 'USDT'
                || $lockedTransaction->network !== 'TRC20'
                || $actual !== $expected
                || ! filled($lockedTransaction->txid)
            ) {
                throw new DomainException('Payout verification failed: asset/network/amount/TxID does not match the approved customer share.');
            }

            $lockedTransaction->forceFill([
                'status' => InvestmentTransaction::STATUS_VERIFIED,
                'verified_by_admin_id' => $admin->getKey(),
                'verified_at' => now(),
            ])->save();

            $lockedPeriod->forceFill([
                'status' => InvestmentProfitPeriod::STATUS_SETTLED,
                'paid_at' => now(),
            ])->save();

            $this->audit($admin, $request, 'INVESTMENT_PROFIT_PAYOUT_SETTLED', $lockedTransaction, [
                'period_status' => $lockedPeriod->status,
                'paid_at' => $lockedPeriod->paid_at?->toIso8601String(),
            ]);
        }, 3);
    }

    public function reject(
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTransaction $transaction,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.transactions.verify'), 403);
        $this->assertPayout($investment, $period, $transaction);

        DB::transaction(function () use ($period, $transaction, $reason, $admin, $request): void {
            $lockedPeriod = InvestmentProfitPeriod::query()->lockForUpdate()->findOrFail($period->getKey());
            $lockedTransaction = InvestmentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());

            if ($lockedTransaction->status !== InvestmentTransaction::STATUS_PENDING) {
                throw new DomainException('Only a pending profit payout can be rejected.');
            }

            $lockedTransaction->forceFill([
                'status' => InvestmentTransaction::STATUS_REJECTED,
                'verified_by_admin_id' => $admin->getKey(),
                'verified_at' => now(),
                'notes' => trim(($lockedTransaction->notes ? $lockedTransaction->notes."\n" : '').'REJECTED: '.$reason),
            ])->save();

            $lockedPeriod->forceFill([
                'status' => InvestmentProfitPeriod::STATUS_APPROVED,
            ])->save();

            $this->audit($admin, $request, 'INVESTMENT_PROFIT_PAYOUT_REJECTED', $lockedTransaction, [
                'reason' => trim($reason),
                'period_status' => $lockedPeriod->status,
            ]);
        }, 3);
    }

    private function assertPeriod(Investment $investment, InvestmentProfitPeriod $period): void
    {
        abort_unless($period->investment_id === $investment->getKey(), 404);
    }

    private function assertPayout(
        Investment $investment,
        InvestmentProfitPeriod $period,
        InvestmentTransaction $transaction
    ): void {
        if (
            $transaction->investment_id !== $investment->getKey()
            || $transaction->investment_profit_period_id !== $period->getKey()
            || $transaction->type !== InvestmentTransaction::TYPE_PROFIT_PAYOUT
        ) {
            throw new DomainException('Investment profit payout transaction not found.');
        }
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentTransaction $transaction,
        array $extra
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT_TRANSACTION',
            targetId: $transaction->getKey(),
            newValues: array_merge([
                'investment_id' => $transaction->investment_id,
                'profit_period_id' => $transaction->investment_profit_period_id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'asset' => $transaction->asset_symbol,
                'network' => $transaction->network,
                'txid' => $transaction->txid,
                'status' => $transaction->status,
            ], $extra),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
