<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Support\FixedDecimal;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentTopUpService
{
    public function record(
        Investment $investment,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentTransaction {
        abort_unless($admin->hasPermission('investments.topups.record'), 403);

        if ($investment->status !== Investment::STATUS_ACTIVE) {
            throw new DomainException('Top-ups are permitted only for active investments.');
        }

        $amount = FixedDecimal::normalize((string) $data['amount']);
        $txid = strtolower(trim((string) $data['txid']));

        if (InvestmentTransaction::query()->where('network', 'TRC20')->where('txid', $txid)->exists()) {
            throw new DomainException('This TRC20 transaction ID is already recorded.');
        }

        try {
            $transaction = InvestmentTransaction::query()->create([
                'uuid' => Str::uuid()->toString(),
                'investment_id' => $investment->getKey(),
                'direction' => InvestmentTransaction::DIRECTION_IN,
                'type' => InvestmentTransaction::TYPE_TOP_UP,
                'asset_symbol' => 'USDT',
                'amount' => $amount,
                'usdt_equivalent' => $amount,
                'network' => 'TRC20',
                'from_address' => $data['sender_address'] ?? null,
                'to_address' => $data['receiving_address'],
                'txid' => $txid,
                'status' => InvestmentTransaction::STATUS_PENDING,
                'occurred_at' => $data['occurred_at'],
                'recorded_by_admin_id' => $admin->getKey(),
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'investment_transactions_network_txid_unique')) {
                throw new DomainException('This TRC20 transaction ID is already recorded.');
            }
            throw $exception;
        }

        $this->audit($admin, $request, 'INVESTMENT_TOP_UP_RECORDED', $transaction, []);
        return $transaction;
    }

    public function verify(
        Investment $investment,
        InvestmentTransaction $transaction,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.topups.verify'), 403);
        $this->assertTopUp($investment, $transaction);

        DB::transaction(function () use ($investment, $transaction, $admin, $request): void {
            $lockedInvestment = Investment::query()->lockForUpdate()->findOrFail($investment->getKey());
            $lockedTransaction = InvestmentTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $this->assertTopUp($lockedInvestment, $lockedTransaction);

            if ($lockedTransaction->status !== InvestmentTransaction::STATUS_PENDING) {
                throw new DomainException('Only a pending top-up can be verified.');
            }

            if ($lockedTransaction->asset_symbol !== 'USDT' || $lockedTransaction->network !== 'TRC20') {
                throw new DomainException('Investment top-ups must be USDT on TRC20.');
            }

            $lockedTransaction->forceFill([
                'status' => InvestmentTransaction::STATUS_VERIFIED,
                'verified_by_admin_id' => $admin->getKey(),
                'verified_at' => now(),
            ])->save();

            $lockedInvestment->forceFill([
                'high_water_mark_usdt' => FixedDecimal::add(
                    (string) $lockedInvestment->high_water_mark_usdt,
                    (string) $lockedTransaction->usdt_equivalent
                ),
                'updated_by_admin_id' => $admin->getKey(),
            ])->save();

            $this->audit($admin, $request, 'INVESTMENT_TOP_UP_VERIFIED', $lockedTransaction, [
                'new_high_water_mark_usdt' => $lockedInvestment->high_water_mark_usdt,
            ]);
        }, 3);
    }

    public function reject(
        Investment $investment,
        InvestmentTransaction $transaction,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless($admin->hasPermission('investments.topups.verify'), 403);
        $this->assertTopUp($investment, $transaction);

        if ($transaction->status !== InvestmentTransaction::STATUS_PENDING) {
            throw new DomainException('Only a pending top-up can be rejected.');
        }

        $transaction->forceFill([
            'status' => InvestmentTransaction::STATUS_REJECTED,
            'verified_by_admin_id' => $admin->getKey(),
            'verified_at' => now(),
            'notes' => trim(($transaction->notes ? $transaction->notes."\n" : '').'REJECTED: '.$reason),
        ])->save();

        $this->audit($admin, $request, 'INVESTMENT_TOP_UP_REJECTED', $transaction, [
            'reason' => trim($reason),
        ]);
    }

    private function assertTopUp(Investment $investment, InvestmentTransaction $transaction): void
    {
        if (
            $transaction->investment_id !== $investment->getKey()
            || $transaction->type !== InvestmentTransaction::TYPE_TOP_UP
        ) {
            throw new DomainException('Investment top-up transaction not found.');
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
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'network' => $transaction->network,
                'txid' => $transaction->txid,
                'status' => $transaction->status,
            ], $extra),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }
}
