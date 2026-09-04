<?php

namespace App\Services\Admin\Investments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\InvestmentAgreement;
use App\Models\InvestmentProfitPeriod;
use App\Models\InvestmentRiskAcceptance;
use App\Models\InvestmentTransaction;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvestmentLifecycleService
{
    public function recordDeposit(
        Investment $investment,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentTransaction {
        abort_unless(
            $admin->hasPermission('investments.deposits.record'),
            403
        );

        if (! in_array(
            $investment->status,
            [
                Investment::STATUS_AWAITING_DEPOSIT,
                Investment::STATUS_DEPOSIT_VERIFICATION,
            ],
            true
        )) {
            throw new DomainException(
                'This investment cannot accept a new deposit record in its current status.'
            );
        }

        if ($this->hasDepositWithStatus(
            $investment,
            InvestmentTransaction::STATUS_VERIFIED
        )) {
            throw new DomainException(
                'A verified deposit already exists for this investment.'
            );
        }

        if ($this->hasDepositWithStatus(
            $investment,
            InvestmentTransaction::STATUS_PENDING
        )) {
            throw new DomainException(
                'A pending deposit is already waiting for verification. Reject it before recording another deposit.'
            );
        }

        $amount = $this->normalizeDecimal(
            (string) $data['amount'],
            8
        );

        $expected = $this->normalizeDecimal(
            (string) $investment->deposit_amount,
            8
        );

        if ($amount !== $expected) {
            throw new DomainException(
                'The USDT deposit amount must exactly match the investment amount.'
            );
        }

        $txid = strtolower(trim((string) $data['txid']));

        if (InvestmentTransaction::query()
            ->where('network', 'TRC20')
            ->where('txid', $txid)
            ->exists()) {
            throw new DomainException(
                'This TRC20 transaction ID is already recorded.'
            );
        }

        try {
            return DB::transaction(
                function () use (
                    $investment,
                    $data,
                    $admin,
                    $request,
                    $amount,
                    $txid
                ): InvestmentTransaction {
                    $locked = Investment::query()
                        ->lockForUpdate()
                        ->findOrFail($investment->getKey());

                    if ($this->hasDepositWithStatus(
                        $locked,
                        InvestmentTransaction::STATUS_VERIFIED
                    )) {
                        throw new DomainException(
                            'A verified deposit already exists for this investment.'
                        );
                    }

                    if ($this->hasDepositWithStatus(
                        $locked,
                        InvestmentTransaction::STATUS_PENDING
                    )) {
                        throw new DomainException(
                            'A pending deposit is already waiting for verification.'
                        );
                    }

                    $transaction = InvestmentTransaction::query()
                        ->create([
                            'uuid' => Str::uuid()->toString(),
                            'investment_id' => $locked->getKey(),
                            'direction' =>
                                InvestmentTransaction::DIRECTION_IN,
                            'type' =>
                                InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT,
                            'asset_symbol' => 'USDT',
                            'amount' => $amount,
                            'usdt_equivalent' => $amount,
                            'network' => 'TRC20',
                            'from_address' =>
                                $data['sender_address'] ?? null,
                            'to_address' =>
                                (string) $data['receiving_address'],
                            'txid' => $txid,
                            'status' =>
                                InvestmentTransaction::STATUS_PENDING,
                            'occurred_at' => $data['occurred_at'],
                            'recorded_by_admin_id' =>
                                $admin->getKey(),
                            'notes' => $data['notes'] ?? null,
                        ]);

                    $locked->forceFill([
                        'status' =>
                            Investment::STATUS_DEPOSIT_VERIFICATION,
                        'updated_by_admin_id' => $admin->getKey(),
                    ])->save();

                    AuditLog::record(
                        actorType: 'ADMIN',
                        actorId: $admin->getKey(),
                        action: 'INVESTMENT_DEPOSIT_RECORDED',
                        module: 'INVESTMENTS',
                        targetType: 'INVESTMENT_TRANSACTION',
                        targetId: $transaction->getKey(),
                        newValues: [
                            'investment_id' => $locked->getKey(),
                            'investment_code' =>
                                $locked->investment_code,
                            'asset' => 'USDT',
                            'amount' => $amount,
                            'network' => 'TRC20',
                            'txid' => $txid,
                            'status' => $transaction->status,
                        ],
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );

                    return $transaction;
                },
                3
            );
        } catch (QueryException $exception) {
            if (str_contains(
                strtolower($exception->getMessage()),
                'investment_transactions_network_txid_unique'
            )) {
                throw new DomainException(
                    'This TRC20 transaction ID is already recorded.'
                );
            }

            throw $exception;
        }
    }

    public function verifyDeposit(
        Investment $investment,
        InvestmentTransaction $transaction,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission('investments.deposits.verify'),
            403
        );

        $this->assertDepositBelongsToInvestment(
            $investment,
            $transaction
        );

        DB::transaction(
            function () use (
                $investment,
                $transaction,
                $admin,
                $request
            ): void {
                $lockedInvestment = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $lockedTransaction = InvestmentTransaction::query()
                    ->lockForUpdate()
                    ->findOrFail($transaction->getKey());

                $this->assertDepositBelongsToInvestment(
                    $lockedInvestment,
                    $lockedTransaction
                );

                if (
                    $lockedTransaction->status
                    !== InvestmentTransaction::STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Only a pending investment deposit can be verified.'
                    );
                }

                $expected = $this->normalizeDecimal(
                    (string) $lockedInvestment->deposit_amount,
                    8
                );

                $actual = $this->normalizeDecimal(
                    (string) $lockedTransaction->amount,
                    8
                );

                if (
                    $lockedTransaction->asset_symbol !== 'USDT'
                    || $lockedTransaction->network !== 'TRC20'
                    || $actual !== $expected
                ) {
                    throw new DomainException(
                        'Deposit verification failed because the asset, network or amount does not match the investment contract.'
                    );
                }

                $anotherVerified = InvestmentTransaction::query()
                    ->where(
                        'investment_id',
                        $lockedInvestment->getKey()
                    )
                    ->where(
                        'type',
                        InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT
                    )
                    ->where(
                        'status',
                        InvestmentTransaction::STATUS_VERIFIED
                    )
                    ->where(
                        'id',
                        '<>',
                        $lockedTransaction->getKey()
                    )
                    ->exists();

                if ($anotherVerified) {
                    throw new DomainException(
                        'Another verified deposit already exists for this investment.'
                    );
                }

                $lockedTransaction->forceFill([
                    'status' =>
                        InvestmentTransaction::STATUS_VERIFIED,
                    'verified_by_admin_id' => $admin->getKey(),
                    'verified_at' => now(),
                ])->save();

                $lockedInvestment->forceFill([
                    'status' =>
                        Investment::STATUS_AWAITING_RISK_ACCEPTANCE,
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'INVESTMENT_DEPOSIT_VERIFIED',
                    module: 'INVESTMENTS',
                    targetType: 'INVESTMENT_TRANSACTION',
                    targetId: $lockedTransaction->getKey(),
                    oldValues: [
                        'status' =>
                            InvestmentTransaction::STATUS_PENDING,
                    ],
                    newValues: [
                        'status' =>
                            InvestmentTransaction::STATUS_VERIFIED,
                        'investment_status' =>
                            $lockedInvestment->status,
                        'txid' => $lockedTransaction->txid,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            },
            3
        );
    }

    public function rejectDeposit(
        Investment $investment,
        InvestmentTransaction $transaction,
        string $reason,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission('investments.deposits.verify'),
            403
        );

        $this->assertDepositBelongsToInvestment(
            $investment,
            $transaction
        );

        DB::transaction(
            function () use (
                $investment,
                $transaction,
                $reason,
                $admin,
                $request
            ): void {
                $lockedInvestment = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                $lockedTransaction = InvestmentTransaction::query()
                    ->lockForUpdate()
                    ->findOrFail($transaction->getKey());

                $this->assertDepositBelongsToInvestment(
                    $lockedInvestment,
                    $lockedTransaction
                );

                if (
                    $lockedTransaction->status
                    !== InvestmentTransaction::STATUS_PENDING
                ) {
                    throw new DomainException(
                        'Only a pending investment deposit can be rejected.'
                    );
                }

                $lockedTransaction->forceFill([
                    'status' =>
                        InvestmentTransaction::STATUS_REJECTED,
                    'notes' => trim(
                        ($lockedTransaction->notes
                            ? $lockedTransaction->notes."\n"
                            : '')
                        .'REJECTED: '.$reason
                    ),
                    'verified_by_admin_id' => $admin->getKey(),
                    'verified_at' => now(),
                ])->save();

                $lockedInvestment->forceFill([
                    'status' => Investment::STATUS_AWAITING_DEPOSIT,
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'INVESTMENT_DEPOSIT_REJECTED',
                    module: 'INVESTMENTS',
                    targetType: 'INVESTMENT_TRANSACTION',
                    targetId: $lockedTransaction->getKey(),
                    oldValues: [
                        'status' =>
                            InvestmentTransaction::STATUS_PENDING,
                    ],
                    newValues: [
                        'status' =>
                            InvestmentTransaction::STATUS_REJECTED,
                        'reason' => $reason,
                        'investment_status' =>
                            $lockedInvestment->status,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            },
            3
        );
    }

    public function recordRiskAcceptance(
        Investment $investment,
        array $data,
        AdminUser $admin,
        Request $request
    ): InvestmentRiskAcceptance {
        abort_unless(
            $admin->hasPermission(
                'investments.risk_acceptance.record'
            ),
            403
        );

        if (! $this->hasVerifiedDeposit($investment)) {
            throw new DomainException(
                'A verified USDT TRC20 deposit is required before customer risk acceptance can be recorded.'
            );
        }

        $version = (string) config(
            'tradex_investments.risk.version'
        );

        if ($investment->riskAcceptances()
            ->where('disclosure_version', $version)
            ->exists()) {
            throw new DomainException(
                'The current crypto risk disclosure version has already been accepted for this investment.'
            );
        }

        return DB::transaction(
            function () use (
                $investment,
                $data,
                $admin,
                $request,
                $version
            ): InvestmentRiskAcceptance {
                $locked = Investment::query()
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                if (! $this->hasVerifiedDeposit($locked)) {
                    throw new DomainException(
                        'The verified deposit gate is no longer satisfied.'
                    );
                }

                $acceptance = InvestmentRiskAcceptance::query()
                    ->create([
                        'uuid' => Str::uuid()->toString(),
                        'investment_id' => $locked->getKey(),
                        'user_id' => $locked->user_id,
                        'disclosure_version' => $version,
                        'evidence_source' =>
                            InvestmentRiskAcceptance::SOURCE_ADMIN_RECORDED,
                        'acceptance_reference' =>
                            (string) $data['acceptance_reference'],
                        'acceptance_payload' => [
                            'ack_read_disclosure' => true,
                            'ack_capital_loss' => true,
                            'ack_crypto_volatility' => true,
                            'ack_stablecoin_risk' => true,
                            'ack_network_cyber_risk' => true,
                            'ack_regulatory_risk' => true,
                            'ack_variable_returns' => true,
                            'ack_early_exit_rule' => true,
                        ],
                        'accepted_at' => $data['accepted_at'],
                        'accepted_ip' =>
                            $data['accepted_ip'] ?? null,
                        'accepted_user_agent' =>
                            $data['accepted_user_agent'] ?? null,
                        'recorded_by_admin_id' =>
                            $admin->getKey(),
                    ]);

                $locked->forceFill([
                    'status' => Investment::STATUS_AWAITING_AGREEMENT,
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action:
                        'INVESTMENT_RISK_ACCEPTANCE_RECORDED',
                    module: 'INVESTMENTS',
                    targetType:
                        'INVESTMENT_RISK_ACCEPTANCE',
                    targetId: $acceptance->getKey(),
                    newValues: [
                        'investment_id' => $locked->getKey(),
                        'disclosure_version' => $version,
                        'evidence_source' =>
                            $acceptance->evidence_source,
                        'acceptance_reference' =>
                            $acceptance->acceptance_reference,
                        'accepted_at' =>
                            $acceptance->accepted_at
                                ?->toIso8601String(),
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $acceptance;
            },
            3
        );
    }

    public function verifyPayoutWallet(
        Investment $investment,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $admin->hasPermission('investments.wallets.verify'),
            403
        );

        $address = trim(
            (string) $investment->payout_wallet_address
        );

        $pattern = (string) config(
            'tradex_investments.deposit.tron_address_pattern'
        );

        if (
            $address === ''
            || preg_match($pattern, $address) !== 1
        ) {
            throw new DomainException(
                'A valid customer USDT TRC20 payout wallet must be configured before verification.'
            );
        }

        $investment->forceFill([
            'payout_wallet_network' => 'TRC20',
            'payout_wallet_verified_at' => now(),
            'payout_wallet_verified_by_admin_id' =>
                $admin->getKey(),
            'updated_by_admin_id' => $admin->getKey(),
        ])->save();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'INVESTMENT_PAYOUT_WALLET_VERIFIED',
            module: 'INVESTMENTS',
            targetType: 'INVESTMENT',
            targetId: $investment->getKey(),
            newValues: [
                'network' => 'TRC20',
                'wallet_tail' => substr($address, -6),
                'verified_at' =>
                    $investment->payout_wallet_verified_at
                        ?->toIso8601String(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    public function readiness(
        Investment $investment
    ): array {
        $riskVersion = (string) config(
            'tradex_investments.risk.version'
        );

        $verifiedDeposit =
            $this->hasVerifiedDeposit($investment);

        $riskAccepted =
            $investment->riskAcceptances()
                ->where('disclosure_version', $riskVersion)
                ->exists();

        $agreement = $investment->agreements()
            ->latest('version')
            ->first();

        $agreementVerified =
            $agreement instanceof InvestmentAgreement
            && $agreement->status
                === InvestmentAgreement::STATUS_VERIFIED
            && $agreement->risk_disclosure_version
                === $riskVersion;

        $productionLegalGateRequired =
            (bool) config(
                'tradex_investments.agreement.production_activation_requires_approved_template',
                false
            );

        $agreementTemplateApproved =
            ! $productionLegalGateRequired
            || (
                $agreement instanceof InvestmentAgreement
                && $agreement->template_status
                    === (string) config(
                        'tradex_investments.agreement.approved_template_status',
                        'APPROVED'
                    )
            );

        $walletVerified =
            $investment->payout_wallet_verified_at !== null;

        return [
            'deposit_verified' => $verifiedDeposit,
            'risk_accepted' => $riskAccepted,
            'agreement_verified' => $agreementVerified,
            'agreement_template_approved' =>
                $agreementTemplateApproved,
            'wallet_verified' => $walletVerified,
            'ready' =>
                $verifiedDeposit
                && $riskAccepted
                && $agreementVerified
                && $agreementTemplateApproved
                && $walletVerified,
            'risk_version' => $riskVersion,
            'agreement' => $agreement,
        ];
    }

    public function activate(
        Investment $investment,
        AdminUser $admin,
        Request $request
    ): Investment {
        abort_unless(
            $admin->hasPermission('investments.activate'),
            403
        );

        return DB::transaction(
            function () use (
                $investment,
                $admin,
                $request
            ): Investment {
                $locked = Investment::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->findOrFail($investment->getKey());

                if ($locked->status === Investment::STATUS_ACTIVE) {
                    throw new DomainException(
                        'This investment is already active.'
                    );
                }

                if (
                    $locked->status
                    !== Investment::STATUS_AWAITING_AGREEMENT
                ) {
                    throw new DomainException(
                        'Only an investment waiting for agreement completion can be activated.'
                    );
                }

                if (
                    ! $locked->product
                    || ! $locked->product->isActive()
                ) {
                    throw new DomainException(
                        'The investment product is not active.'
                    );
                }

                if ($locked->fixed_return_guaranteed) {
                    throw new DomainException(
                        'Fixed or guaranteed returns are not permitted by the frozen Investment V1 model.'
                    );
                }

                if (
                    $locked->customer_profit_share_bps
                    + $locked->company_profit_share_bps
                    !== 10000
                ) {
                    throw new DomainException(
                        'Profit-share basis points must total 100%.'
                    );
                }

                $readiness = $this->readiness($locked);

                if (! $readiness['ready']) {
                    if (
                        ! $readiness[
                            'agreement_template_approved'
                        ]
                    ) {
                        throw new DomainException(
                            'Production activation is blocked because the verified signed agreement was generated from a template that was not APPROVED after qualified legal review.'
                        );
                    }

                    throw new DomainException(
                        'Activation gates are incomplete: verified deposit, current risk acceptance, verified signed agreement and verified TRC20 payout wallet are all required.'
                    );
                }

                if ($locked->profitPeriods()->exists()) {
                    throw new DomainException(
                        'Profit periods already exist; activation cannot be repeated.'
                    );
                }

                $start = CarbonImmutable::today();

                $maturity = $start->addYears(
                    (int) $locked->investment_period_years
                );

                $principalSettlement = $maturity;

                if (
                    $locked->settlement_mode
                    === Investment::SETTLEMENT_CUSTOM_DATE
                ) {
                    if (! $locked->custom_settlement_date) {
                        throw new DomainException(
                            'Custom principal settlement mode requires a settlement date.'
                        );
                    }

                    $custom = CarbonImmutable::parse(
                        $locked->custom_settlement_date
                    )->startOfDay();

                    if ($custom->lessThanOrEqualTo($start)) {
                        throw new DomainException(
                            'The custom principal settlement date must be after the activation date.'
                        );
                    }

                    if ($custom->lessThan($maturity)) {
                        throw new DomainException(
                            'Normal principal settlement cannot be scheduled before maturity. Use the early-termination workflow for an earlier exit.'
                        );
                    }

                    $principalSettlement = $custom;
                }

                $locked->forceFill([
                    'status' => Investment::STATUS_ACTIVE,
                    'start_date' => $start->toDateString(),
                    'maturity_date' =>
                        $maturity->toDateString(),
                    'principal_settlement_date' =>
                        $principalSettlement->toDateString(),
                    'principal_settlement_status' =>
                        Investment::PRINCIPAL_NOT_DUE,
                    'high_water_mark_usdt' =>
                        $this->normalizeDecimal(
                            (string) $locked->deposit_amount,
                            8
                        ),
                    'loss_carryforward_usdt' => '0.00000000',
                    'activated_at' => now(),
                    'activated_by_admin_id' => $admin->getKey(),
                    'updated_by_admin_id' => $admin->getKey(),
                ])->save();

                $this->generateProfitPeriods(
                    $locked,
                    $start,
                    $maturity
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'INVESTMENT_ACTIVATED',
                    module: 'INVESTMENTS',
                    targetType: 'INVESTMENT',
                    targetId: $locked->getKey(),
                    newValues: [
                        'status' => $locked->status,
                        'start_date' =>
                            $locked->start_date?->toDateString(),
                        'maturity_date' =>
                            $locked->maturity_date?->toDateString(),
                        'principal_settlement_date' =>
                            $locked->principal_settlement_date
                                ?->toDateString(),
                        'profit_review_interval_months' =>
                            $locked->profit_review_interval_months,
                        'profit_period_count' =>
                            $locked->profitPeriods()->count(),
                        'customer_profit_share_bps' =>
                            $locked->customer_profit_share_bps,
                        'company_profit_share_bps' =>
                            $locked->company_profit_share_bps,
                        'fixed_return_guaranteed' => false,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $locked->fresh([
                    'product',
                    'profitPeriods',
                ]);
            },
            3
        );
    }

    private function generateProfitPeriods(
        Investment $investment,
        CarbonImmutable $start,
        CarbonImmutable $maturity
    ): void {
        $interval =
            (int) $investment->profit_review_interval_months;

        if (! in_array($interval, [2, 4, 6], true)) {
            throw new DomainException(
                'Unsupported profit review interval.'
            );
        }

        $totalMonths =
            (int) $investment->investment_period_years
            * 12;

        if ($totalMonths % $interval !== 0) {
            throw new DomainException(
                'Investment period does not divide evenly by the selected profit review interval.'
            );
        }

        $periodCount = intdiv(
            $totalMonths,
            $interval
        );

        $cursor = $start;

        for (
            $sequence = 1;
            $sequence <= $periodCount;
            $sequence++
        ) {
            $periodEnd = $cursor
                ->addMonthsNoOverflow($interval);

            if (
                $sequence === $periodCount
                && ! $periodEnd->equalTo($maturity)
            ) {
                $periodEnd = $maturity;
            }

            InvestmentProfitPeriod::query()->create([
                'investment_id' => $investment->getKey(),
                'sequence' => $sequence,
                'period_start' => $cursor->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' =>
                    InvestmentProfitPeriod::STATUS_UPCOMING,
                'opening_equity_usdt' => '0.00000000',
                'closing_equity_usdt' => '0.00000000',
                'gross_realized_profit_usdt' => '0.00000000',
                'realized_losses_usdt' => '0.00000000',
                'approved_costs_usdt' => '0.00000000',
                'loss_carryforward_open_usdt' => '0.00000000',
                'loss_carryforward_close_usdt' => '0.00000000',
                'high_water_mark_open_usdt' =>
                    $sequence === 1
                        ? $this->normalizeDecimal(
                            (string) $investment->deposit_amount,
                            8
                        )
                        : '0.00000000',
                'high_water_mark_close_usdt' => '0.00000000',
                'eligible_net_profit_usdt' => '0.00000000',
                'customer_profit_share_usdt' => '0.00000000',
                'company_profit_share_usdt' => '0.00000000',
            ]);

            $cursor = $periodEnd;
        }
    }

    private function hasVerifiedDeposit(
        Investment $investment
    ): bool {
        return $this->hasDepositWithStatus(
            $investment,
            InvestmentTransaction::STATUS_VERIFIED
        );
    }

    private function hasDepositWithStatus(
        Investment $investment,
        string $status
    ): bool {
        return $investment->transactions()
            ->where(
                'type',
                InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT
            )
            ->where('status', $status)
            ->where('asset_symbol', 'USDT')
            ->where('network', 'TRC20')
            ->exists();
    }

    private function assertDepositBelongsToInvestment(
        Investment $investment,
        InvestmentTransaction $transaction
    ): void {
        if (
            $transaction->investment_id
                !== $investment->getKey()
            || $transaction->type
                !== InvestmentTransaction::TYPE_INVESTMENT_DEPOSIT
        ) {
            throw new DomainException(
                'Investment deposit transaction not found.'
            );
        }
    }

    private function normalizeDecimal(
        string $value,
        int $scale
    ): string {
        $value = trim($value);

        if (! preg_match(
            '/^\d+(?:\.\d+)?$/',
            $value
        )) {
            throw new DomainException(
                'Invalid decimal amount.'
            );
        }

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            ''
        );

        $whole = ltrim($whole, '0');
        $whole = $whole === '' ? '0' : $whole;

        $fraction = substr(
            str_pad($fraction, $scale, '0'),
            0,
            $scale
        );

        return $whole.'.'.$fraction;
    }
}
