<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentApprovedCost;
use App\Models\InvestmentAssetAllocation;
use App\Models\InvestmentFinancialAdjustment;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentSettlement;
use App\Models\InvestmentSettlementAsset;
use App\Models\InvestmentTrade;
use App\Models\InvestmentTransaction;
use App\Support\FixedDecimal;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentSettlementService
{
    public function __construct(
        private readonly InvestmentSettlementProfitService $profitService
    ) {
    }

    public function initiateEarlyTermination(
        Investment $investment,
        string $reason,
        AdminUser $admin,
        Request $request
    ): InvestmentSettlement {
        abort_unless(
            $admin->hasPermission('investments.early_termination.review'),
            403
        );

        if ($investment->status !== Investment::STATUS_ACTIVE) {
            throw new DomainException(
                'Early termination can be initiated only for an active investment.'
            );
        }

        if ($investment->settlements()->exists()) {
            throw new DomainException(
                'A terminal settlement already exists for this investment.'
            );
        }

        if ((int) $investment->early_exit_return_bps !== 8000) {
            throw new DomainException(
                'Investment V1 early termination must preserve the frozen 80% in-kind return rule.'
            );
        }

        if (
            $investment->maturity_date
            && today()->gte(CarbonImmutable::parse($investment->maturity_date))
        ) {
            throw new DomainException(
                'Early termination is unavailable on or after maturity. Use the maturity settlement workflow.'
            );
        }

        return DB::transaction(
            function () use (
                $investment,
                $reason,
                $admin,
                $request
            ): InvestmentSettlement {
                $locked = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                if ($locked->status !== Investment::STATUS_ACTIVE) {
                    throw new DomainException(
                        'Investment state changed before early termination could be frozen.'
                    );
                }

                if ($locked->settlements()->exists()) {
                    throw new DomainException(
                        'A terminal settlement already exists for this investment.'
                    );
                }

                $cutoff = now();

                $settlement = InvestmentSettlement::query()->create([
                    'uuid' => Str::uuid()->toString(),
                    'investment_id' => $locked->getKey(),
                    'type' => InvestmentSettlement::TYPE_EARLY_TERMINATION,
                    'status' => InvestmentSettlement::STATUS_DRAFT,
                    'cutoff_at' => $cutoff,
                    'due_at' => $cutoff->toDateString(),
                    'return_bps' => (int) $locked->early_exit_return_bps,
                    'reason' => trim($reason),
                    'created_by_admin_id' => $admin->getKey(),
                ]);

                $locked->forceFill([
                    'status' => Investment::STATUS_EARLY_TERMINATION_REVIEW,
                    'principal_settlement_status' =>
                        Investment::PRINCIPAL_ON_HOLD,
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'INVESTMENT_EARLY_TERMINATION_FROZEN',
                    $settlement,
                    [
                        'cutoff_at' =>
                            $settlement->cutoff_at?->toIso8601String(),
                        'return_bps' => $settlement->return_bps,
                        'reason' => $settlement->reason,
                    ]
                );

                return $settlement;
            },
            3
        );
    }

    public function prepareMaturity(
        Investment $investment,
        AdminUser $admin,
        Request $request
    ): InvestmentSettlement {
        abort_unless(
            $admin->hasPermission('investments.settlements.prepare'),
            403
        );

        if (! $investment->maturity_date) {
            throw new DomainException('Investment maturity date is missing.');
        }

        $maturityDate = CarbonImmutable::parse(
            $investment->maturity_date
        )->startOfDay();

        if (today()->lt($maturityDate)) {
            throw new DomainException(
                'Maturity settlement cannot be prepared before the contracted maturity date.'
            );
        }

        if (! in_array(
            $investment->status,
            [
                Investment::STATUS_ACTIVE,
                Investment::STATUS_MATURED,
            ],
            true
        )) {
            throw new DomainException(
                'Maturity settlement is unavailable in the current investment state.'
            );
        }

        return DB::transaction(
            function () use (
                $investment,
                $admin,
                $request
            ): InvestmentSettlement {
                $locked = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $settlement = $locked->settlements()
                    ->lockForUpdate()
                    ->first();

                if (
                    $settlement
                    && $settlement->type
                        !== InvestmentSettlement::TYPE_MATURITY
                ) {
                    throw new DomainException(
                        'An early-termination settlement already exists for this investment.'
                    );
                }

                if (! $settlement) {
                    $maturity = CarbonImmutable::parse(
                        $locked->maturity_date
                    );

                    $dueAt = $locked->principal_settlement_date
                        ? CarbonImmutable::parse(
                            $locked->principal_settlement_date
                        )
                        : $maturity;

                    if ($dueAt->lt($maturity)) {
                        throw new DomainException(
                            'Normal principal settlement cannot occur before maturity. Use the early-termination workflow instead.'
                        );
                    }

                    $settlement = InvestmentSettlement::query()
                        ->create([
                            'uuid' => Str::uuid()->toString(),
                            'investment_id' => $locked->getKey(),
                            'type' =>
                                InvestmentSettlement::TYPE_MATURITY,
                            'status' =>
                                InvestmentSettlement::STATUS_DRAFT,
                            'cutoff_at' => $maturity->endOfDay(),
                            'due_at' => $dueAt->toDateString(),
                            'return_bps' => 10000,
                            'created_by_admin_id' =>
                                $admin->getKey(),
                        ]);
                }

                $locked->forceFill([
                    'status' => Investment::STATUS_MATURED,
                    'principal_settlement_status' =>
                        today()->gte($settlement->due_at)
                            ? Investment::PRINCIPAL_DUE
                            : Investment::PRINCIPAL_NOT_DUE,
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                return $this->prepareLocked(
                    $locked,
                    $settlement,
                    $admin,
                    $request
                );
            },
            3
        );
    }

    public function prepare(
        Investment $investment,
        InvestmentSettlement $settlement,
        AdminUser $admin,
        Request $request
    ): InvestmentSettlement {
        abort_unless(
            $admin->hasPermission('investments.settlements.prepare'),
            403
        );

        $this->assertSettlement($investment, $settlement);

        return DB::transaction(
            function () use (
                $investment,
                $settlement,
                $admin,
                $request
            ): InvestmentSettlement {
                $lockedInvestment = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $lockedSettlement = InvestmentSettlement::query()
                    ->lockForUpdate()
                    ->findOrFail($settlement->getKey());

                $this->assertSettlement(
                    $lockedInvestment,
                    $lockedSettlement
                );

                return $this->prepareLocked(
                    $lockedInvestment,
                    $lockedSettlement,
                    $admin,
                    $request
                );
            },
            3
        );
    }

    public function recordWallet(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        array $data,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.wallets.record'
            ),
            403
        );

        $this->assertAsset(
            $investment,
            $settlement,
            $asset
        );

        if (
            $settlement->status
            !== InvestmentSettlement::STATUS_PREPARED
        ) {
            throw new DomainException(
                'Settlement wallets can be recorded only after preparation and before Finance approval.'
            );
        }

        $network = trim((string) $data['payout_network']);
        $wallet = trim((string) $data['destination_wallet']);

        if (
            $asset->asset_network !== null
            && strcasecmp(
                $network,
                (string) $asset->asset_network
            ) !== 0
        ) {
            throw new DomainException(
                'The payout network must match the frozen attributable asset network. Cross-chain conversion is not part of Settlement V1.'
            );
        }

        if (strcasecmp($network, 'TRC20') === 0) {
            $pattern = (string) config(
                'tradex_investments.deposit.tron_address_pattern'
            );

            if (
                $pattern !== ''
                && preg_match($pattern, $wallet) !== 1
            ) {
                throw new DomainException(
                    'A valid TRON/TRC20 destination wallet is required for this asset.'
                );
            }
        }

        $asset->forceFill([
            'payout_network' => $network,
            'destination_wallet' => $wallet,
            'destination_wallet_sha256' =>
                hash('sha256', $wallet),
            'wallet_reference' =>
                trim((string) $data['wallet_reference']),
            'wallet_status' =>
                InvestmentSettlementAsset::WALLET_PENDING,
            'wallet_recorded_by_admin_id' =>
                $admin->getKey(),
            'wallet_verified_by_admin_id' => null,
            'wallet_verified_at' => null,
            'status' =>
                InvestmentSettlementAsset::STATUS_AWAITING_WALLET,
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_SETTLEMENT_WALLET_RECORDED',
            $settlement,
            [
                'settlement_asset_id' => $asset->getKey(),
                'asset_symbol' => $asset->asset_symbol,
                'payout_network' => $network,
                'wallet_sha256' =>
                    $asset->destination_wallet_sha256,
                'wallet_reference' =>
                    $asset->wallet_reference,
            ]
        );
    }

    public function verifyWallet(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.wallets.verify'
            ),
            403
        );

        $this->assertAsset(
            $investment,
            $settlement,
            $asset
        );

        if (
            $settlement->status
            !== InvestmentSettlement::STATUS_PREPARED
        ) {
            throw new DomainException(
                'Wallet verification must be completed before settlement approval.'
            );
        }

        if (
            $asset->wallet_status
                !== InvestmentSettlementAsset::WALLET_PENDING
            || ! filled($asset->destination_wallet)
            || ! filled($asset->destination_wallet_sha256)
            || ! filled($asset->payout_network)
        ) {
            throw new DomainException(
                'A pending settlement wallet record is required before Finance verification.'
            );
        }

        if (
            ! hash_equals(
                (string) $asset->destination_wallet_sha256,
                hash(
                    'sha256',
                    (string) $asset->destination_wallet
                )
            )
        ) {
            throw new DomainException(
                'Settlement destination-wallet integrity verification failed.'
            );
        }

        $asset->forceFill([
            'wallet_status' =>
                InvestmentSettlementAsset::WALLET_VERIFIED,
            'wallet_verified_by_admin_id' =>
                $admin->getKey(),
            'wallet_verified_at' => now(),
            'status' =>
                InvestmentSettlementAsset::STATUS_READY,
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_SETTLEMENT_WALLET_VERIFIED',
            $settlement,
            [
                'settlement_asset_id' => $asset->getKey(),
                'asset_symbol' => $asset->asset_symbol,
                'wallet_sha256' =>
                    $asset->destination_wallet_sha256,
            ]
        );
    }

    public function approve(
        Investment $investment,
        InvestmentSettlement $settlement,
        AdminUser $admin,
        Request $request
    ): InvestmentSettlement {
        abort_unless(
            $admin->hasPermission(
                'investments.principal_settlement.approve'
            ),
            403
        );

        $this->assertSettlement(
            $investment,
            $settlement
        );

        return DB::transaction(
            function () use (
                $investment,
                $settlement,
                $admin,
                $request
            ): InvestmentSettlement {
                $lockedInvestment = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $lockedSettlement = InvestmentSettlement::query()
                    ->lockForUpdate()
                    ->findOrFail($settlement->getKey());

                $this->assertSettlement(
                    $lockedInvestment,
                    $lockedSettlement
                );

                if (
                    $lockedSettlement->status
                    !== InvestmentSettlement::STATUS_PREPARED
                ) {
                    throw new DomainException(
                        'Only a prepared settlement can receive Finance approval.'
                    );
                }

                if (
                    $lockedSettlement->prepared_by_admin_id
                        === $admin->getKey()
                    && ! $admin->isSuperAdmin()
                ) {
                    throw new DomainException(
                        'Maker-checker separation requires a different Finance Admin to approve the settlement.'
                    );
                }

                if (today()->lt($lockedSettlement->due_at)) {
                    throw new DomainException(
                        'Principal settlement approval is not due yet.'
                    );
                }

                $assets = $lockedSettlement->assets()
                    ->lockForUpdate()
                    ->orderBy('id')
                    ->get();

                if ($assets->isEmpty()) {
                    throw new DomainException(
                        'Prepared settlement has no attributable customer assets.'
                    );
                }

                foreach ($assets as $asset) {
                    if (
                        $asset->wallet_status
                            !== InvestmentSettlementAsset::WALLET_VERIFIED
                        || $asset->status
                            !== InvestmentSettlementAsset::STATUS_READY
                        || ! filled($asset->destination_wallet)
                        || ! filled(
                            $asset->destination_wallet_sha256
                        )
                        || ! hash_equals(
                            (string) $asset
                                ->destination_wallet_sha256,
                            hash(
                                'sha256',
                                (string) $asset
                                    ->destination_wallet
                            )
                        )
                    ) {
                        throw new DomainException(
                            'Every settlement asset requires a Finance-verified destination wallet before approval.'
                        );
                    }
                }

                [$snapshot, $sourceHash] =
                    $this->buildSnapshot(
                        $lockedInvestment,
                        $lockedSettlement
                    );

                if (
                    ! filled(
                        $lockedSettlement
                            ->calculation_source_sha256
                    )
                    || ! hash_equals(
                        (string) $lockedSettlement
                            ->calculation_source_sha256,
                        $sourceHash
                    )
                ) {
                    throw new DomainException(
                        'Investment ledger data changed after settlement preparation. Re-prepare the settlement before approval.'
                    );
                }

                $lockedSettlement->forceFill([
                    'status' =>
                        InvestmentSettlement::STATUS_APPROVED,
                    'approved_by_admin_id' =>
                        $admin->getKey(),
                    'approved_at' => now(),
                ])->save();

                $lockedInvestment->forceFill([
                    'principal_settlement_status' =>
                        Investment::PRINCIPAL_APPROVED,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'INVESTMENT_SETTLEMENT_APPROVED',
                    $lockedSettlement,
                    [
                        'maker_admin_id' =>
                            $lockedSettlement
                                ->prepared_by_admin_id,
                        'checker_admin_id' =>
                            $admin->getKey(),
                        'super_admin_override' =>
                            $lockedSettlement
                                ->prepared_by_admin_id
                                === $admin->getKey()
                            && $admin->isSuperAdmin(),
                        'source_sha256' => $sourceHash,
                    ]
                );

                return $lockedSettlement->fresh([
                    'assets',
                ]);
            },
            3
        );
    }

    public function recordAssetPayout(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentTransaction {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.payments.record'
            ),
            403
        );
        abort_unless(
            $admin->hasPermission(
                'investments.transactions.record'
            ),
            403
        );

        $this->assertAsset(
            $investment,
            $settlement,
            $asset
        );

        if (
            ! in_array(
                $settlement->status,
                [
                    InvestmentSettlement::STATUS_APPROVED,
                    InvestmentSettlement::STATUS_IN_PROGRESS,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Settlement asset payouts can be recorded only after Finance approval.'
            );
        }

        if (
            $asset->wallet_status
                !== InvestmentSettlementAsset::WALLET_VERIFIED
            || $asset->status
                !== InvestmentSettlementAsset::STATUS_READY
        ) {
            throw new DomainException(
                'This settlement asset is not ready for payment recording.'
            );
        }

        $feeAsset = strtoupper(
            trim(
                (string) (
                    $data['network_fee_asset_symbol']
                    ?? $asset->asset_symbol
                )
            )
        );

        $feeUnits = FixedDecimal::normalize(
            (string) $data['network_fee_units'],
            18
        );

        $deduct = (bool) (
            $data['deduct_fee_from_customer']
            ?? false
        );

        if (FixedDecimal::isNegative($feeUnits, 18)) {
            throw new DomainException(
                'Network fee units cannot be negative.'
            );
        }

        if (
            $deduct
            && $feeAsset
                !== strtoupper(
                    (string) $asset->asset_symbol
                )
        ) {
            throw new DomainException(
                'A network fee can be deducted from customer settlement quantity only when the fee asset is the same settlement asset.'
            );
        }

        $netUnits = $deduct
            ? FixedDecimal::sub(
                (string) $asset->customer_return_units,
                $feeUnits,
                18
            )
            : FixedDecimal::normalize(
                (string) $asset->customer_return_units,
                18
            );

        if (! FixedDecimal::isPositive($netUnits, 18)) {
            throw new DomainException(
                'Network-fee deduction must leave a positive customer transfer quantity.'
            );
        }

        $txid = strtolower(
            trim((string) $data['txid'])
        );

        $network = (string) $asset->payout_network;

        if (
            InvestmentTransaction::query()
                ->where('network', $network)
                ->where('txid', $txid)
                ->exists()
        ) {
            throw new DomainException(
                'This settlement transaction ID is already recorded on the selected network.'
            );
        }

        try {
            return DB::transaction(
                function () use (
                    $investment,
                    $settlement,
                    $asset,
                    $data,
                    $admin,
                    $request,
                    $feeAsset,
                    $feeUnits,
                    $deduct,
                    $netUnits,
                    $txid,
                    $network
                ): InvestmentTransaction {
                    $lockedSettlement =
                        InvestmentSettlement::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $settlement->getKey()
                            );

                    $lockedAsset =
                        InvestmentSettlementAsset::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $asset->getKey()
                            );

                    if (
                        ! in_array(
                            $lockedSettlement->status,
                            [
                                InvestmentSettlement::STATUS_APPROVED,
                                InvestmentSettlement::STATUS_IN_PROGRESS,
                            ],
                            true
                        )
                    ) {
                        throw new DomainException(
                            'Settlement state changed before payment recording.'
                        );
                    }

                    if (
                        $lockedAsset->status
                        !== InvestmentSettlementAsset::STATUS_READY
                    ) {
                        throw new DomainException(
                            'Settlement asset state changed before payment recording.'
                        );
                    }

                    $type =
                        $lockedSettlement->type
                            === InvestmentSettlement::TYPE_MATURITY
                        ? InvestmentTransaction::TYPE_PRINCIPAL_RETURN
                        : InvestmentTransaction::TYPE_EARLY_EXIT_IN_KIND;

                    $transaction =
                        InvestmentTransaction::query()
                            ->create([
                                'uuid' =>
                                    Str::uuid()->toString(),
                                'investment_id' =>
                                    $investment->getKey(),
                                'investment_settlement_id' =>
                                    $lockedSettlement
                                        ->getKey(),
                                'investment_settlement_asset_id' =>
                                    $lockedAsset
                                        ->getKey(),
                                'direction' =>
                                    InvestmentTransaction::DIRECTION_OUT,
                                'type' => $type,
                                'asset_symbol' =>
                                    $lockedAsset
                                        ->asset_symbol,
                                'amount' => $netUnits,
                                'usdt_equivalent' => null,
                                'network' => $network,
                                'from_address' =>
                                    $data['from_address']
                                    ?? null,
                                'to_address' =>
                                    $lockedAsset
                                        ->destination_wallet,
                                'txid' => $txid,
                                'status' =>
                                    InvestmentTransaction::STATUS_PENDING,
                                'occurred_at' =>
                                    $data['occurred_at'],
                                'recorded_by_admin_id' =>
                                    $admin->getKey(),
                                'notes' =>
                                    $data['notes']
                                    ?? null,
                            ]);

                    $lockedAsset->forceFill([
                        'network_fee_asset_symbol' =>
                            $feeAsset,
                        'network_fee_units' =>
                            $feeUnits,
                        'network_fee_deducted_from_customer' =>
                            $deduct,
                        'net_transfer_units' =>
                            $netUnits,
                        'status' =>
                            InvestmentSettlementAsset::STATUS_PAYMENT_RECORDED,
                    ])->save();

                    $lockedSettlement->forceFill([
                        'status' =>
                            InvestmentSettlement::STATUS_IN_PROGRESS,
                    ])->save();

                    $this->audit(
                        $admin,
                        $request,
                        'INVESTMENT_SETTLEMENT_ASSET_PAYMENT_RECORDED',
                        $lockedSettlement,
                        [
                            'settlement_asset_id' =>
                                $lockedAsset->getKey(),
                            'transaction_id' =>
                                $transaction->getKey(),
                            'asset_symbol' =>
                                $lockedAsset->asset_symbol,
                            'gross_customer_units' =>
                                (string) $lockedAsset
                                    ->customer_return_units,
                            'network_fee_asset_symbol' =>
                                $feeAsset,
                            'network_fee_units' =>
                                $feeUnits,
                            'network_fee_deducted_from_customer' =>
                                $deduct,
                            'net_transfer_units' =>
                                $netUnits,
                            'network' => $network,
                            'txid' => $txid,
                        ]
                    );

                    return $transaction;
                },
                3
            );
        } catch (QueryException $exception) {
            if (
                str_contains(
                    strtolower(
                        $exception->getMessage()
                    ),
                    'investment_transactions_network_txid_unique'
                )
            ) {
                throw new DomainException(
                    'This settlement transaction ID is already recorded on the selected network.'
                );
            }

            throw $exception;
        }
    }

    public function verifyAssetPayout(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentTransaction $transaction,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.payments.verify'
            ),
            403
        );
        abort_unless(
            $admin->hasPermission(
                'investments.transactions.verify'
            ),
            403
        );

        $this->assertAsset(
            $investment,
            $settlement,
            $asset
        );

        $this->assertAssetTransaction(
            $investment,
            $settlement,
            $asset,
            $transaction
        );

        DB::transaction(
            function () use (
                $investment,
                $settlement,
                $asset,
                $transaction,
                $admin,
                $request
            ): void {
                $lockedSettlement =
                    InvestmentSettlement::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $settlement->getKey()
                        );

                $lockedAsset =
                    InvestmentSettlementAsset::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $asset->getKey()
                        );

                $lockedTransaction =
                    InvestmentTransaction::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $transaction->getKey()
                        );

                $this->assertAssetTransaction(
                    $investment,
                    $lockedSettlement,
                    $lockedAsset,
                    $lockedTransaction
                );

                if (
                    $lockedAsset->status
                        !== InvestmentSettlementAsset::STATUS_PAYMENT_RECORDED
                    || $lockedTransaction->status
                        !== InvestmentTransaction::STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Only a pending recorded settlement asset payment can be verified.'
                    );
                }

                if (
                    ! filled(
                        $lockedTransaction->txid
                    )
                    || $lockedTransaction->asset_symbol
                        !== $lockedAsset->asset_symbol
                    || $lockedTransaction->network
                        !== $lockedAsset->payout_network
                    || FixedDecimal::normalize(
                        (string) $lockedTransaction
                            ->amount,
                        18
                    )
                        !== FixedDecimal::normalize(
                            (string) $lockedAsset
                                ->net_transfer_units,
                            18
                        )
                    || (string) $lockedTransaction
                        ->to_address
                        !== (string) $lockedAsset
                            ->destination_wallet
                ) {
                    throw new DomainException(
                        'Settlement payment verification failed: asset/network/amount/wallet/TxID does not match the approved settlement asset.'
                    );
                }

                $lockedTransaction->forceFill([
                    'status' =>
                        InvestmentTransaction::STATUS_VERIFIED,
                    'verified_by_admin_id' =>
                        $admin->getKey(),
                    'verified_at' => now(),
                ])->save();

                $lockedAsset->forceFill([
                    'status' =>
                        InvestmentSettlementAsset::STATUS_SETTLED,
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'INVESTMENT_SETTLEMENT_ASSET_PAYMENT_VERIFIED',
                    $lockedSettlement,
                    [
                        'settlement_asset_id' =>
                            $lockedAsset->getKey(),
                        'transaction_id' =>
                            $lockedTransaction
                                ->getKey(),
                        'txid' =>
                            $lockedTransaction->txid,
                    ]
                );

                $this->finalizeIfComplete(
                    $investment,
                    $lockedSettlement,
                    $admin,
                    $request
                );
            },
            3
        );
    }

    public function recordTerminalProfitPayout(
        Investment $investment,
        InvestmentSettlement $settlement,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentTransaction {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.payments.record'
            ),
            403
        );
        abort_unless(
            $admin->hasPermission(
                'investments.transactions.record'
            ),
            403
        );

        $this->assertSettlement(
            $investment,
            $settlement
        );

        if (
            $settlement->type
            !== InvestmentSettlement::TYPE_EARLY_TERMINATION
        ) {
            throw new DomainException(
                'Terminal accrued-profit payout applies only to early termination.'
            );
        }

        if (
            ! in_array(
                $settlement->status,
                [
                    InvestmentSettlement::STATUS_APPROVED,
                    InvestmentSettlement::STATUS_IN_PROGRESS,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Terminal profit payout can be recorded only after settlement approval.'
            );
        }

        $amount = FixedDecimal::normalize(
            (string) $settlement
                ->customer_profit_share_usdt
        );

        if (! FixedDecimal::isPositive($amount)) {
            throw new DomainException(
                'This early-termination settlement has no customer terminal profit to pay.'
            );
        }

        if (
            ! $investment->payout_wallet_verified_at
            || $investment->payout_wallet_network
                !== 'TRC20'
            || ! filled(
                $investment->payout_wallet_address
            )
        ) {
            throw new DomainException(
                'The customer verified USDT TRC20 payout wallet is required for terminal profit payout.'
            );
        }

        $existing = InvestmentTransaction::query()
            ->where(
                'investment_settlement_id',
                $settlement->getKey()
            )
            ->where(
                'type',
                InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
            )
            ->whereIn(
                'status',
                [
                    InvestmentTransaction::STATUS_PENDING,
                    InvestmentTransaction::STATUS_VERIFIED,
                ]
            )
            ->exists();

        if ($existing) {
            throw new DomainException(
                'A terminal profit payout already exists for this settlement.'
            );
        }

        $txid = strtolower(
            trim((string) $data['txid'])
        );

        if (
            InvestmentTransaction::query()
                ->where('network', 'TRC20')
                ->where('txid', $txid)
                ->exists()
        ) {
            throw new DomainException(
                'This TRC20 transaction ID is already recorded.'
            );
        }

        try {
            $transaction =
                InvestmentTransaction::query()
                    ->create([
                        'uuid' =>
                            Str::uuid()->toString(),
                        'investment_id' =>
                            $investment->getKey(),
                        'investment_settlement_id' =>
                            $settlement->getKey(),
                        'investment_settlement_asset_id' =>
                            null,
                        'direction' =>
                            InvestmentTransaction::DIRECTION_OUT,
                        'type' =>
                            InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT,
                        'asset_symbol' => 'USDT',
                        'amount' => $amount,
                        'usdt_equivalent' => $amount,
                        'network' => 'TRC20',
                        'from_address' =>
                            $data['from_address']
                            ?? null,
                        'to_address' =>
                            $investment
                                ->payout_wallet_address,
                        'txid' => $txid,
                        'status' =>
                            InvestmentTransaction::STATUS_PENDING,
                        'occurred_at' =>
                            $data['occurred_at'],
                        'recorded_by_admin_id' =>
                            $admin->getKey(),
                        'notes' =>
                            $data['notes']
                            ?? null,
                    ]);

            $settlement->forceFill([
                'status' =>
                    InvestmentSettlement::STATUS_IN_PROGRESS,
            ])->save();

            $this->audit(
                $admin,
                $request,
                'INVESTMENT_TERMINAL_PROFIT_PAYOUT_RECORDED',
                $settlement,
                [
                    'transaction_id' =>
                        $transaction->getKey(),
                    'amount_usdt' => $amount,
                    'network' => 'TRC20',
                    'txid' => $txid,
                ]
            );

            return $transaction;
        } catch (QueryException $exception) {
            if (
                str_contains(
                    strtolower(
                        $exception->getMessage()
                    ),
                    'investment_transactions_network_txid_unique'
                )
            ) {
                throw new DomainException(
                    'This TRC20 transaction ID is already recorded.'
                );
            }

            throw $exception;
        }
    }

    public function verifyTerminalProfitPayout(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentTransaction $transaction,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.payments.verify'
            ),
            403
        );
        abort_unless(
            $admin->hasPermission(
                'investments.transactions.verify'
            ),
            403
        );

        $this->assertTerminalProfitTransaction(
            $investment,
            $settlement,
            $transaction
        );

        DB::transaction(
            function () use (
                $investment,
                $settlement,
                $transaction,
                $admin,
                $request
            ): void {
                $lockedSettlement =
                    InvestmentSettlement::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $settlement->getKey()
                        );

                $lockedTransaction =
                    InvestmentTransaction::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $transaction->getKey()
                        );

                $this->assertTerminalProfitTransaction(
                    $investment,
                    $lockedSettlement,
                    $lockedTransaction
                );

                $expected = FixedDecimal::normalize(
                    (string) $lockedSettlement
                        ->customer_profit_share_usdt
                );

                if (
                    $lockedTransaction->status
                        !== InvestmentTransaction::STATUS_PENDING
                    || $lockedTransaction->asset_symbol
                        !== 'USDT'
                    || $lockedTransaction->network
                        !== 'TRC20'
                    || FixedDecimal::normalize(
                        (string) $lockedTransaction
                            ->amount
                    ) !== $expected
                    || (string) $lockedTransaction
                        ->to_address
                        !== (string) $investment
                            ->payout_wallet_address
                    || ! filled(
                        $lockedTransaction->txid
                    )
                ) {
                    throw new DomainException(
                        'Terminal profit payout verification failed.'
                    );
                }

                $lockedTransaction->forceFill([
                    'status' =>
                        InvestmentTransaction::STATUS_VERIFIED,
                    'verified_by_admin_id' =>
                        $admin->getKey(),
                    'verified_at' => now(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'INVESTMENT_TERMINAL_PROFIT_PAYOUT_VERIFIED',
                    $lockedSettlement,
                    [
                        'transaction_id' =>
                            $lockedTransaction
                                ->getKey(),
                        'amount_usdt' => $expected,
                        'txid' =>
                            $lockedTransaction->txid,
                    ]
                );

                $this->finalizeIfComplete(
                    $investment,
                    $lockedSettlement,
                    $admin,
                    $request
                );
            },
            3
        );
    }

    public function close(
        Investment $investment,
        InvestmentSettlement $settlement,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission(
                'investments.settlements.close'
            ),
            403
        );

        $this->assertSettlement(
            $investment,
            $settlement
        );

        DB::transaction(
            function () use (
                $investment,
                $settlement,
                $admin,
                $request
            ): void {
                $lockedInvestment =
                    Investment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $investment->getKey()
                        );

                $lockedSettlement =
                    InvestmentSettlement::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $settlement->getKey()
                        );

                if (
                    $lockedSettlement->status
                    !== InvestmentSettlement::STATUS_SETTLED
                ) {
                    throw new DomainException(
                        'Only a fully settled terminal settlement can close an investment.'
                    );
                }

                if (
                    ! in_array(
                        $lockedInvestment->status,
                        [
                            Investment::STATUS_MATURED,
                            Investment::STATUS_EARLY_TERMINATED,
                        ],
                        true
                    )
                ) {
                    throw new DomainException(
                        'Investment terminal state is not ready for closure.'
                    );
                }

                $oldStatus =
                    $lockedInvestment->status;

                $lockedInvestment->forceFill([
                    'status' =>
                        Investment::STATUS_CLOSED,
                    'principal_settlement_status' =>
                        Investment::PRINCIPAL_SETTLED,
                    'closed_at' => now(),
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();

                $this->audit(
                    $admin,
                    $request,
                    'INVESTMENT_CLOSED_AFTER_SETTLEMENT',
                    $lockedSettlement,
                    [
                        'previous_investment_status' =>
                            $oldStatus,
                        'investment_status' =>
                            Investment::STATUS_CLOSED,
                        'closed_at' =>
                            $lockedInvestment
                                ->closed_at
                                ?->toIso8601String(),
                    ]
                );
            },
            3
        );
    }

    public function assertFinancialLedgerMutable(
        Investment $investment
    ): void {
        if (
            $investment->settlements()
                ->whereNotNull('approved_at')
                ->exists()
        ) {
            throw new DomainException(
                'The terminal settlement has Finance approval. The investment financial ledger is now immutable.'
            );
        }
    }

    private function prepareLocked(
        Investment $investment,
        InvestmentSettlement $settlement,
        AdminUser $admin,
        Request $request
    ): InvestmentSettlement {
        if (
            in_array(
                $settlement->status,
                [
                    InvestmentSettlement::STATUS_APPROVED,
                    InvestmentSettlement::STATUS_IN_PROGRESS,
                    InvestmentSettlement::STATUS_SETTLED,
                ],
                true
            )
        ) {
            throw new DomainException(
                'An approved or settled terminal settlement is immutable.'
            );
        }

        if (
            $settlement->type
                === InvestmentSettlement::TYPE_EARLY_TERMINATION
            && $investment->status
                !== Investment::STATUS_EARLY_TERMINATION_REVIEW
        ) {
            throw new DomainException(
                'Early-termination settlement requires the investment to remain frozen in review state.'
            );
        }

        $pendingTopUpExists = InvestmentTransaction::query()
            ->where('investment_id', $investment->getKey())
            ->where('type', InvestmentTransaction::TYPE_TOP_UP)
            ->where('status', InvestmentTransaction::STATUS_PENDING)
            ->where('occurred_at', '<=', $settlement->cutoff_at)
            ->exists();

        if ($pendingTopUpExists) {
            throw new DomainException(
                'A pending investment top-up exists on or before the settlement cutoff. Verify or reject it before settlement preparation.'
            );
        }

        if (
            $settlement->type
            === InvestmentSettlement::TYPE_MATURITY
        ) {
            if (
                ! $investment->maturity_date
                || today()->lt(
                    CarbonImmutable::parse(
                        $investment->maturity_date
                    )
                )
            ) {
                throw new DomainException(
                    'Maturity settlement is not due for preparation.'
                );
            }

            if (
                $investment->status
                !== Investment::STATUS_MATURED
            ) {
                $investment->forceFill([
                    'status' =>
                        Investment::STATUS_MATURED,
                    'updated_by_admin_id' =>
                        $admin->getKey(),
                ])->save();
            }
        }

        [
            $snapshot,
            $sourceHash,
            $assetRows,
        ] = $this->buildSnapshot(
            $investment,
            $settlement,
            true
        );

        $settlement->assets()->delete();

        foreach ($assetRows as $row) {
            InvestmentSettlementAsset::query()
                ->create(
                    array_merge(
                        $row,
                        [
                            'uuid' =>
                                Str::uuid()->toString(),
                            'investment_settlement_id' =>
                                $settlement->getKey(),
                            'investment_id' =>
                                $investment->getKey(),
                            'wallet_status' =>
                                InvestmentSettlementAsset::WALLET_NOT_PROVIDED,
                            'status' =>
                                InvestmentSettlementAsset::STATUS_AWAITING_WALLET,
                        ]
                    )
                );
        }

        $profit = $snapshot['profit'];

        $settlement->forceFill([
            'status' =>
                InvestmentSettlement::STATUS_PREPARED,
            'calculation_version' =>
                InvestmentSettlement::CALCULATION_VERSION,
            'calculation_source_sha256' =>
                $sourceHash,
            'calculation_snapshot' =>
                $snapshot,
            'reference_capital_usdt' =>
                $profit['reference_capital_usdt'],
            'gross_realized_profit_usdt' =>
                $profit['gross_realized_profit_usdt'],
            'realized_losses_usdt' =>
                $profit['realized_losses_usdt'],
            'approved_costs_usdt' =>
                $profit['approved_costs_usdt'],
            'loss_carryforward_open_usdt' =>
                $profit['loss_carryforward_open_usdt'],
            'loss_carryforward_close_usdt' =>
                $profit['loss_carryforward_close_usdt'],
            'eligible_net_profit_usdt' =>
                $profit['eligible_net_profit_usdt'],
            'customer_profit_share_usdt' =>
                $profit['customer_profit_share_usdt'],
            'company_profit_share_usdt' =>
                $profit['company_profit_share_usdt'],
            'rounding_adjustment_usdt' =>
                $profit['rounding_adjustment_usdt'],
            'prepared_by_admin_id' =>
                $admin->getKey(),
            'prepared_at' => now(),
            'approved_by_admin_id' => null,
            'approved_at' => null,
        ])->save();

        $investment->forceFill([
            'principal_settlement_status' =>
                Investment::PRINCIPAL_PAYMENT_PREPARED,
            'updated_by_admin_id' =>
                $admin->getKey(),
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_SETTLEMENT_PREPARED',
            $settlement,
            [
                'type' => $settlement->type,
                'asset_count' => count($assetRows),
                'return_bps' =>
                    $settlement->return_bps,
                'source_sha256' => $sourceHash,
                'customer_profit_share_usdt' =>
                    $profit[
                        'customer_profit_share_usdt'
                    ],
                'company_profit_share_usdt' =>
                    $profit[
                        'company_profit_share_usdt'
                    ],
            ]
        );

        return $settlement->fresh([
            'assets',
        ]);
    }

    private function buildSnapshot(
        Investment $investment,
        InvestmentSettlement $settlement,
        bool $includeRows = false
    ): array {
        $profit =
            $settlement->type
                === InvestmentSettlement::TYPE_EARLY_TERMINATION
            ? $this->profitService
                ->earlyTerminationSnapshot(
                    $investment,
                    $settlement
                )
            : $this->profitService
                ->maturitySnapshot(
                    $investment
                );

        $assetState = $this->customerAssetState(
            $investment,
            CarbonImmutable::parse(
                $settlement->cutoff_at
            ),
            $settlement->type
                === InvestmentSettlement::TYPE_EARLY_TERMINATION
                ? (string) $profit[
                    'eligible_net_profit_usdt'
                ]
                : '0.00000000',
            (int) $settlement->return_bps
        );

        if ($assetState['rows'] === []) {
            throw new DomainException(
                'No positive attributable customer asset units remain for terminal settlement.'
            );
        }

        $snapshot = [
            'calculation_version' =>
                InvestmentSettlement::CALCULATION_VERSION,
            'settlement_type' =>
                $settlement->type,
            'cutoff_at' =>
                $settlement->cutoff_at
                    ?->toIso8601String(),
            'due_at' =>
                $settlement->due_at
                    ?->toDateString(),
            'return_bps' =>
                (int) $settlement->return_bps,
            'profit' => $profit,
            'asset_source' =>
                $assetState['source'],
            'assets' =>
                array_map(
                    fn (array $row) => [
                        'asset_identity_sha256' =>
                            $row[
                                'asset_identity_sha256'
                            ],
                        'asset_symbol' =>
                            $row['asset_symbol'],
                        'asset_network' =>
                            $row['asset_network'],
                        'contract_address' =>
                            $row['contract_address'],
                        'gross_attributable_units' =>
                            $row[
                                'gross_attributable_units'
                            ],
                        'return_bps' =>
                            $row['return_bps'],
                        'customer_return_units' =>
                            $row[
                                'customer_return_units'
                            ],
                        'early_exit_adjustment_units' =>
                            $row[
                                'early_exit_adjustment_units'
                            ],
                        'allocation_snapshot' =>
                            $row[
                                'allocation_snapshot'
                            ],
                    ],
                    $assetState['rows']
                ),
        ];

        $encoded = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_PRESERVE_ZERO_FRACTION
        );

        if (! is_string($encoded)) {
            throw new DomainException(
                'Unable to serialize terminal settlement snapshot.'
            );
        }

        $sourceHash = hash(
            'sha256',
            $encoded
        );

        if ($includeRows) {
            return [
                $snapshot,
                $sourceHash,
                $assetState['rows'],
            ];
        }

        return [
            $snapshot,
            $sourceHash,
        ];
    }

    private function customerAssetState(
        Investment $investment,
        CarbonImmutable $cutoff,
        string $terminalEligibleProfit,
        int $returnBps
    ): array {
        $reversalAdjustments =
            InvestmentFinancialAdjustment::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'status',
                    InvestmentFinancialAdjustment::STATUS_VERIFIED
                )
                ->where(
                    'adjustment_type',
                    InvestmentFinancialAdjustment::TYPE_REVERSAL
                )
                ->orderBy('id')
                ->get();

        $reversedTradeIds =
            $reversalAdjustments
                ->where(
                    'target_type',
                    InvestmentFinancialAdjustment::TARGET_TRADE
                )
                ->pluck('target_id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        $reversedCostIds =
            $reversalAdjustments
                ->where(
                    'target_type',
                    InvestmentFinancialAdjustment::TARGET_COST
                )
                ->pluck('target_id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();

        $inflows =
            InvestmentTransaction::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'status',
                    InvestmentTransaction::STATUS_VERIFIED
                )
                ->whereIn(
                    'type',
                    [
                        InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT,
                        InvestmentTransaction::TYPE_TOP_UP,
                    ]
                )
                ->where(
                    'occurred_at',
                    '<=',
                    $cutoff
                )
                ->orderBy('id')
                ->get();

        $cash = FixedDecimal::sum(
            $inflows
                ->pluck('amount')
                ->map(
                    fn ($value) =>
                        (string) $value
                )
                ->all()
        );

        $trades = InvestmentTrade::query()
            ->where(
                'investment_id',
                $investment->getKey()
            )
            ->where(
                'status',
                InvestmentTrade::STATUS_VERIFIED
            )
            ->where(
                'executed_at',
                '<=',
                $cutoff
            )
            ->when(
                $reversedTradeIds !== [],
                fn ($query) =>
                    $query->whereNotIn(
                        'id',
                        $reversedTradeIds
                    )
            )
            ->orderBy('executed_at')
            ->orderBy('id')
            ->get();

        foreach ($trades as $trade) {
            if (
                $trade->action
                === InvestmentTrade::ACTION_BUY
            ) {
                $cash = FixedDecimal::sub(
                    $cash,
                    FixedDecimal::add(
                        (string) $trade
                            ->gross_value_usdt,
                        (string) $trade
                            ->fee_usdt
                    )
                );
            } elseif (
                $trade->action
                === InvestmentTrade::ACTION_SELL
            ) {
                $cash = FixedDecimal::add(
                    $cash,
                    FixedDecimal::sub(
                        (string) $trade
                            ->gross_value_usdt,
                        (string) $trade
                            ->fee_usdt
                    )
                );
            }
        }

        $standaloneCosts =
            InvestmentApprovedCost::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'status',
                    InvestmentApprovedCost::STATUS_VERIFIED
                )
                ->whereNull(
                    'investment_trade_id'
                )
                ->where(
                    'occurred_at',
                    '<=',
                    $cutoff
                )
                ->when(
                    $reversedCostIds !== [],
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $reversedCostIds
                        )
                )
                ->orderBy('id')
                ->get();

        $cash = FixedDecimal::sub(
            $cash,
            FixedDecimal::sum(
                $standaloneCosts
                    ->pluck('amount_usdt')
                    ->map(
                        fn ($value) =>
                            (string) $value
                    )
                    ->all()
            )
        );

        $nonReversalAdjustments =
            InvestmentFinancialAdjustment::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'status',
                    InvestmentFinancialAdjustment::STATUS_VERIFIED
                )
                ->where(
                    'adjustment_type',
                    InvestmentFinancialAdjustment::TYPE_ADJUSTMENT
                )
                ->orderBy('id')
                ->get();

        $cash = FixedDecimal::sub(
            $cash,
            FixedDecimal::sum(
                $nonReversalAdjustments
                    ->pluck(
                        'approved_cost_delta_usdt'
                    )
                    ->map(
                        fn ($value) =>
                            (string) $value
                    )
                    ->all()
            )
        );

        $verifiedProfitPayouts =
            InvestmentTransaction::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'type',
                    InvestmentTransaction::TYPE_PROFIT_PAYOUT
                )
                ->where(
                    'status',
                    InvestmentTransaction::STATUS_VERIFIED
                )
                ->where(
                    'occurred_at',
                    '<=',
                    $cutoff
                )
                ->orderBy('id')
                ->get();

        $cash = FixedDecimal::sub(
            $cash,
            FixedDecimal::sum(
                $verifiedProfitPayouts
                    ->pluck('amount')
                    ->map(
                        fn ($value) =>
                            (string) $value
                    )
                    ->all()
            )
        );

        $companySharePeriods =
            InvestmentProfitPeriod::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->whereDate(
                    'period_end',
                    '<=',
                    $cutoff->toDateString()
                )
                ->whereIn(
                    'status',
                    [
                        InvestmentProfitPeriod::STATUS_SETTLED,
                        InvestmentProfitPeriod::STATUS_NO_DISTRIBUTION,
                        InvestmentProfitPeriod::STATUS_LOSS_CARRIED_FORWARD,
                        InvestmentProfitPeriod::STATUS_PAID,
                        InvestmentProfitPeriod::STATUS_NO_PROFIT,
                    ]
                )
                ->orderBy('sequence')
                ->get();

        $cash = FixedDecimal::sub(
            $cash,
            FixedDecimal::sum(
                $companySharePeriods
                    ->pluck(
                        'company_profit_share_usdt'
                    )
                    ->map(
                        fn ($value) =>
                            (string) $value
                    )
                    ->all()
            )
        );

        $terminalReserve =
            FixedDecimal::normalize(
                $terminalEligibleProfit
            );

        if (
            FixedDecimal::compare(
                $cash,
                $terminalReserve
            ) < 0
        ) {
            throw new DomainException(
                'The reconstructed USDT cash ledger is insufficient to reserve the terminal eligible profit. Settlement cannot safely separate accrued profit from underlying principal/assets.'
            );
        }

        $cashAfterReserve =
            FixedDecimal::sub(
                $cash,
                $terminalReserve
            );

        if (
            FixedDecimal::isNegative(
                $cashAfterReserve
            )
        ) {
            throw new DomainException(
                'The reconstructed customer USDT cash ledger is negative. Reconcile the investment ledger before terminal settlement.'
            );
        }

        $groups = [];

        $allocations =
            InvestmentAssetAllocation::query()
                ->where(
                    'investment_id',
                    $investment->getKey()
                )
                ->where(
                    'remaining_units',
                    '>',
                    0
                )
                ->orderBy('acquired_at')
                ->orderBy('id')
                ->get();

        foreach ($allocations as $allocation) {
            $this->appendAssetGroup(
                $groups,
                (string) $allocation
                    ->asset_symbol,
                $allocation->network,
                $allocation
                    ->contract_address,
                (string) $allocation
                    ->remaining_units,
                [
                    'source' =>
                        'ASSET_ALLOCATION',
                    'allocation_id' =>
                        $allocation->getKey(),
                    'source_trade_id' =>
                        $allocation
                            ->source_trade_id,
                    'source_transaction_id' =>
                        $allocation
                            ->source_transaction_id,
                    'remaining_units' =>
                        FixedDecimal::normalize(
                            (string) $allocation
                                ->remaining_units,
                            18
                        ),
                    'acquired_at' =>
                        $allocation
                            ->acquired_at
                            ?->toIso8601String(),
                ]
            );
        }

        if (
            FixedDecimal::isPositive(
                $cashAfterReserve
            )
        ) {
            $this->appendAssetGroup(
                $groups,
                'USDT',
                'TRC20',
                null,
                FixedDecimal::normalize(
                    $cashAfterReserve,
                    18
                ),
                [
                    'source' =>
                        'RECONSTRUCTED_USDT_CASH',
                    'units' =>
                        FixedDecimal::normalize(
                            $cashAfterReserve,
                            18
                        ),
                    'terminal_profit_reserve_usdt' =>
                        $terminalReserve,
                ]
            );
        }

        $rows = [];

        foreach ($groups as $group) {
            $gross = FixedDecimal::normalize(
                $group['units'],
                18
            );

            if (
                ! FixedDecimal::isPositive(
                    $gross,
                    18
                )
            ) {
                continue;
            }

            $customerReturn =
                FixedDecimal::multiplyBasisPoints(
                    $gross,
                    $returnBps,
                    18
                );

            $rows[] = [
                'asset_identity_sha256' =>
                    $group['identity'],
                'asset_symbol' =>
                    $group['asset_symbol'],
                'asset_network' =>
                    $group['network'],
                'contract_address' =>
                    $group['contract_address'],
                'gross_attributable_units' =>
                    $gross,
                'return_bps' =>
                    $returnBps,
                'customer_return_units' =>
                    $customerReturn,
                'early_exit_adjustment_units' =>
                    FixedDecimal::sub(
                        $gross,
                        $customerReturn,
                        18
                    ),
                'network_fee_asset_symbol' =>
                    null,
                'network_fee_units' =>
                    '0.000000000000000000',
                'network_fee_deducted_from_customer' =>
                    false,
                'net_transfer_units' =>
                    null,
                'allocation_snapshot' =>
                    $group['sources'],
            ];
        }

        usort(
            $rows,
            fn (array $a, array $b) =>
                [
                    $a['asset_symbol'],
                    $a['asset_network'] ?? '',
                    $a['contract_address'] ?? '',
                ]
                <=>
                [
                    $b['asset_symbol'],
                    $b['asset_network'] ?? '',
                    $b['contract_address'] ?? '',
                ]
        );

        return [
            'rows' => $rows,
            'source' => [
                'cutoff_at' =>
                    $cutoff->toIso8601String(),
                'verified_inflows' =>
                    $inflows->map(
                        fn (
                            InvestmentTransaction $tx
                        ) => [
                            'id' =>
                                $tx->getKey(),
                            'type' =>
                                $tx->type,
                            'asset_symbol' =>
                                $tx->asset_symbol,
                            'amount' =>
                                (string) $tx
                                    ->amount,
                            'network' =>
                                $tx->network,
                            'txid' =>
                                $tx->txid,
                            'occurred_at' =>
                                $tx->occurred_at
                                    ?->toIso8601String(),
                        ]
                    )->all(),
                'verified_trades' =>
                    $trades->map(
                        fn (
                            InvestmentTrade $trade
                        ) => [
                            'id' =>
                                $trade->getKey(),
                            'action' =>
                                $trade->action,
                            'asset_symbol' =>
                                $trade->asset_symbol,
                            'gross_value_usdt' =>
                                (string) $trade
                                    ->gross_value_usdt,
                            'fee_usdt' =>
                                (string) $trade
                                    ->fee_usdt,
                            'executed_at' =>
                                $trade->executed_at
                                    ?->toIso8601String(),
                        ]
                    )->all(),
                'standalone_costs' =>
                    $standaloneCosts->map(
                        fn (
                            InvestmentApprovedCost $cost
                        ) => [
                            'id' =>
                                $cost->getKey(),
                            'amount_usdt' =>
                                (string) $cost
                                    ->amount_usdt,
                            'occurred_at' =>
                                $cost->occurred_at
                                    ?->toIso8601String(),
                        ]
                    )->all(),
                'verified_profit_payouts' =>
                    $verifiedProfitPayouts
                        ->map(
                            fn (
                                InvestmentTransaction $tx
                            ) => [
                                'id' =>
                                    $tx->getKey(),
                                'amount' =>
                                    (string) $tx
                                        ->amount,
                                'txid' =>
                                    $tx->txid,
                            ]
                        )->all(),
                'company_profit_shares' =>
                    $companySharePeriods
                        ->map(
                            fn (
                                InvestmentProfitPeriod $period
                            ) => [
                                'id' =>
                                    $period->getKey(),
                                'sequence' =>
                                    $period->sequence,
                                'company_profit_share_usdt' =>
                                    (string) $period
                                        ->company_profit_share_usdt,
                                'status' =>
                                    $period->status,
                            ]
                        )->all(),
                'reversed_trade_ids' =>
                    $reversedTradeIds,
                'reversed_cost_ids' =>
                    $reversedCostIds,
                'terminal_profit_reserve_usdt' =>
                    $terminalReserve,
                'reconstructed_usdt_before_reserve' =>
                    $cash,
                'reconstructed_usdt_after_reserve' =>
                    $cashAfterReserve,
            ],
        ];
    }

    private function appendAssetGroup(
        array &$groups,
        string $assetSymbol,
        ?string $network,
        ?string $contractAddress,
        string $units,
        array $source
    ): void {
        $symbol = strtoupper(
            trim($assetSymbol)
        );

        $network =
            $network !== null
            && trim($network) !== ''
            ? trim($network)
            : null;

        $contractAddress =
            $contractAddress !== null
            && trim($contractAddress) !== ''
            ? trim($contractAddress)
            : null;

        $identity = hash(
            'sha256',
            implode(
                '|',
                [
                    $symbol,
                    strtolower(
                        (string) $network
                    ),
                    strtolower(
                        (string) $contractAddress
                    ),
                ]
            )
        );

        if (! isset($groups[$identity])) {
            $groups[$identity] = [
                'identity' => $identity,
                'asset_symbol' => $symbol,
                'network' => $network,
                'contract_address' =>
                    $contractAddress,
                'units' =>
                    '0.000000000000000000',
                'sources' => [],
            ];
        }

        $groups[$identity]['units'] =
            FixedDecimal::add(
                $groups[$identity]['units'],
                $units,
                18
            );

        $groups[$identity]['sources'][] =
            $source;
    }

    private function finalizeIfComplete(
        Investment $investment,
        InvestmentSettlement $settlement,
        AdminUser $admin,
        Request $request
    ): void {
        if (
            $settlement->assets()
                ->where(
                    'status',
                    '<>',
                    InvestmentSettlementAsset::STATUS_SETTLED
                )
                ->exists()
        ) {
            return;
        }

        $customerProfit =
            FixedDecimal::normalize(
                (string) $settlement
                    ->customer_profit_share_usdt
            );

        if (
            FixedDecimal::isPositive(
                $customerProfit
            )
        ) {
            $terminalProfitVerified =
                InvestmentTransaction::query()
                    ->where(
                        'investment_settlement_id',
                        $settlement->getKey()
                    )
                    ->where(
                        'type',
                        InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
                    )
                    ->where(
                        'status',
                        InvestmentTransaction::STATUS_VERIFIED
                    )
                    ->exists();

            if (! $terminalProfitVerified) {
                return;
            }
        }

        $lockedInvestment =
            Investment::query()
                ->lockForUpdate()
                ->findOrFail(
                    $investment->getKey()
                );

        $settlement->forceFill([
            'status' =>
                InvestmentSettlement::STATUS_SETTLED,
            'settled_at' => now(),
        ])->save();

        $lockedInvestment->forceFill([
            'status' =>
                $settlement->type
                    === InvestmentSettlement::TYPE_EARLY_TERMINATION
                ? Investment::STATUS_EARLY_TERMINATED
                : Investment::STATUS_MATURED,
            'principal_settlement_status' =>
                Investment::PRINCIPAL_SETTLED,
            'updated_by_admin_id' =>
                $admin->getKey(),
        ])->save();

        $this->audit(
            $admin,
            $request,
            'INVESTMENT_TERMINAL_SETTLEMENT_COMPLETED',
            $settlement,
            [
                'investment_status' =>
                    $lockedInvestment->status,
                'settled_at' =>
                    $settlement->settled_at
                        ?->toIso8601String(),
            ]
        );
    }

    private function assertSettlement(
        Investment $investment,
        InvestmentSettlement $settlement
    ): void {
        abort_unless(
            $settlement->investment_id
                === $investment->getKey(),
            404
        );
    }

    private function assertAsset(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset
    ): void {
        $this->assertSettlement(
            $investment,
            $settlement
        );

        abort_unless(
            $asset->investment_id
                === $investment->getKey()
            && $asset->investment_settlement_id
                === $settlement->getKey(),
            404
        );
    }

    private function assertAssetTransaction(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentSettlementAsset $asset,
        InvestmentTransaction $transaction
    ): void {
        if (
            $transaction->investment_id
                !== $investment->getKey()
            || $transaction->investment_settlement_id
                !== $settlement->getKey()
            || $transaction->investment_settlement_asset_id
                !== $asset->getKey()
            || ! in_array(
                $transaction->type,
                [
                    InvestmentTransaction::TYPE_PRINCIPAL_RETURN,
                    InvestmentTransaction::TYPE_EARLY_EXIT_IN_KIND,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Settlement asset transaction not found.'
            );
        }
    }

    private function assertTerminalProfitTransaction(
        Investment $investment,
        InvestmentSettlement $settlement,
        InvestmentTransaction $transaction
    ): void {
        if (
            $transaction->investment_id
                !== $investment->getKey()
            || $transaction->investment_settlement_id
                !== $settlement->getKey()
            || $transaction->investment_settlement_asset_id
                !== null
            || $transaction->type
                !== InvestmentTransaction::TYPE_TERMINAL_PROFIT_PAYOUT
        ) {
            throw new DomainException(
                'Terminal profit payout transaction not found.'
            );
        }
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        InvestmentSettlement $settlement,
        array $values
    ): void {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: $action,
            module: 'INVESTMENTS',
            targetType:
                'INVESTMENT_SETTLEMENT',
            targetId:
                $settlement->getKey(),
            newValues:
                array_merge(
                    [
                        'investment_id' =>
                            $settlement
                                ->investment_id,
                        'settlement_uuid' =>
                            $settlement->uuid,
                        'type' =>
                            $settlement->type,
                        'status' =>
                            $settlement->status,
                    ],
                    $values
                ),
            ipAddress: $request->ip(),
            userAgent:
                $request->userAgent()
        );
    }
}
