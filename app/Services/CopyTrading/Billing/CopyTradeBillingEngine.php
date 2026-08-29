<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeBillingBalanceEvent;
use App\Models\CopyTradeBillingCycle;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBillingSnapshot;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeEntitlementPeriod;
use App\Models\CopyTradeVerifiedBalanceSnapshot;
use App\Services\CopyTrading\CopyTradeBenefitService;
use App\Services\CopyTrading\CopyTradeProductCode;
use DomainException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CopyTradeBillingEngine
{
    public function __construct(
        private readonly CopyTradeBillingPricingService $pricing,
        private readonly CopyTradeBillingCalendarService $calendar,
        private readonly CopyTradeBillingProrationService $proration,
        private readonly CopyTradeBillingInvoiceService $invoices,
        private readonly CopyTradeBillingPaymentService $payments,
        private readonly CopyTradeBenefitService $benefits
    ) {
    }

    /**
     * Initialize billing without bypassing ADMIN-23 activation.
     *
     * VERIFIED + PAID_REQUIRED:
     *   create the frozen prorated activation invoice before activation.
     *   After a cleared payment is linked, ADMIN-23 can re-evaluate and activate.
     *
     * ACTIVE:
     *   lifetime-free -> no billing
     *   30-day-free -> free cycle until the exact free-period end
     *   paid -> use an existing paid activation cycle, or safely bootstrap
     *           a legacy paid cycle for accounts activated before ADMIN-24.
     */
    public function initializeAccountBilling(
        CopyTradeBrokerAccount $account
    ): ?CopyTradeBillingInvoice {
        $account->loadMissing([
            'user',
            'brokerMarket',
            'accountBenefit',
            'entitlementPeriods.userEntitlement.payment',
        ]);

        if (
            ! in_array(
                $account->status,
                [
                    CopyTradeBrokerAccount::STATUS_VERIFIED,
                    CopyTradeBrokerAccount::STATUS_ACTIVE,
                    CopyTradeBrokerAccount::STATUS_SUSPENDED,
                ],
                true
            )
        ) {
            throw new DomainException(
                'Copy Trade billing can only initialize for a verified or operational broker account.'
            );
        }

        $existingCycle = CopyTradeBillingCycle::query()
            ->where(
                'broker_account_id',
                $account->getKey()
            )
            ->latest('period_end')
            ->first();

        if ($existingCycle) {
            return CopyTradeBillingInvoice::query()
                ->where(
                    'billing_cycle_id',
                    $existingCycle->getKey()
                )
                ->latest('issued_at')
                ->first();
        }

        if ($account->status === CopyTradeBrokerAccount::STATUS_VERIFIED) {
            return $this->initializeVerifiedAccount(
                $account
            );
        }

        $benefit = $account->accountBenefit;

        if (! $benefit instanceof CopyTradeAccountBenefit) {
            throw new DomainException(
                'Active Copy Trade account benefit is missing.'
            );
        }

        if (
            $benefit->benefit_type
            === CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1
        ) {
            return null;
        }

        if (
            $benefit->benefit_type
            === CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1
        ) {
            $period = $account->entitlementPeriods
                ->first(
                    fn (
                        CopyTradeEntitlementPeriod $item
                    ): bool =>
                        $item->entitlement_type
                        === CopyTradeEntitlementPeriod::TYPE_THIRTY_DAY_FREE
                );

            if (
                ! $period
                || ! $period->ends_at
            ) {
                throw new DomainException(
                    'The 30-day Copy Trade free entitlement period is incomplete.'
                );
            }

            if (now()->lt($period->ends_at)) {
                $this->ensureFreeCycle(
                    $account,
                    CarbonImmutable::instance(
                        $period->starts_at
                    ),
                    CarbonImmutable::instance(
                        $period->ends_at
                    )
                );

                return null;
            }

            return $this->issueInitialCharge(
                $account,
                CarbonImmutable::instance(
                    $period->ends_at
                ),
                CopyTradeProductCode::monthly(
                    (string) $account
                        ->brokerMarket
                        ->market_category,
                    (int) $benefit->account_slot
                )
            );
        }

        /*
         * A paid-required account that is already ACTIVE necessarily passed
         * ADMIN-23 with a payment-backed cleared entitlement. If ADMIN-24 did
         * not yet exist, do not double-charge its first period. Bootstrap a
         * paid cycle from the existing evidence and bill normally at renewal.
         */
        $this->bootstrapLegacyPaidCycle(
            $account
        );

        return null;
    }

    public function evaluateExternalDepositUpgrade(
        CopyTradeBrokerAccount $account,
        CopyTradeBillingBalanceEvent $balanceEvent,
        ?AdminUser $admin = null
    ): ?CopyTradeBillingInvoice {
        $account->loadMissing([
            'brokerMarket',
            'accountBenefit',
        ]);

        if (! $account->isActive()) {
            throw new DomainException(
                'External-deposit upgrade billing requires an ACTIVE Copy Trade account.'
            );
        }

        $benefit = $account->accountBenefit;

        if (
            $benefit?->benefit_type
            === CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1
        ) {
            return null;
        }

        $cycle = CopyTradeBillingCycle::query()
            ->where(
                'broker_account_id',
                $account->getKey()
            )
            ->whereIn('status', [
                CopyTradeBillingCycle::STATUS_OPEN,
                CopyTradeBillingCycle::STATUS_PAID,
                CopyTradeBillingCycle::STATUS_GRACE,
            ])
            ->latest('period_start')
            ->first();

        if (! $cycle instanceof CopyTradeBillingCycle) {
            throw new DomainException(
                'No active Copy Trade billing cycle is available for an external-deposit upgrade.'
            );
        }

        $balanceEvent->loadMissing(
            'balanceSnapshot'
        );

        if (
            (int) $balanceEvent->broker_account_id
            !== (int) $account->getKey()
            || $balanceEvent->event_type
                !== CopyTradeBillingBalanceEvent::TYPE_EXTERNAL_DEPOSIT
            || (int) $balanceEvent->deposit_amount_minor <= 0
        ) {
            throw new DomainException(
                'A valid immutable external-deposit billing event is required for a tier upgrade.'
            );
        }

        $balance = $balanceEvent->balanceSnapshot;

        if (
            ! $balance
            || (int) $balance->broker_account_id
                !== (int) $account->getKey()
        ) {
            throw new DomainException(
                'External-deposit event balance evidence is invalid.'
            );
        }

        $eventAt = CarbonImmutable::instance(
            $balanceEvent->occurred_at
        );

        $baseline =
            CopyTradeBillingSnapshot::query()
                ->where(
                    'billing_cycle_id',
                    $cycle->getKey()
                )
                ->whereIn(
                    'purpose',
                    [
                        CopyTradeBillingSnapshot::PURPOSE_INITIAL,
                        CopyTradeBillingSnapshot::PURPOSE_RENEWAL,
                        CopyTradeBillingSnapshot::PURPOSE_BASELINE,
                    ]
                )
                ->oldest('event_at')
                ->first();

        if (! $baseline) {
            throw new DomainException(
                'The current billing cycle is missing its immutable baseline balance snapshot.'
            );
        }

        $depositWindowStart =
            $cycle->effective_start_at
                ? CarbonImmutable::instance(
                    $cycle->effective_start_at
                )
                : CarbonImmutable::instance(
                    $cycle->period_start
                );

        $cumulativeDeposits =
            (int) CopyTradeBillingBalanceEvent::query()
                ->where(
                    'broker_account_id',
                    $account->getKey()
                )
                ->where(
                    'event_type',
                    CopyTradeBillingBalanceEvent::TYPE_EXTERNAL_DEPOSIT
                )
                ->whereBetween(
                    'occurred_at',
                    [
                        $depositWindowStart,
                        $eventAt,
                    ]
                )
                ->sum(
                    'deposit_amount_minor'
                );

        /*
         * Mid-period tier upgrades are deposit-driven only:
         * - positive trading P/L must not manufacture an upgrade;
         * - losses/withdrawals may keep actual VBB below the deposit-only
         *   projection and therefore prevent a higher tier;
         * - once upgraded, later losses/withdrawals never refund/downgrade
         *   the current period because active_full_fee_minor is monotonic.
         */
        $depositOnlyProjection =
            (int) $baseline
                ->billing_eligible_balance_minor
            + $cumulativeDeposits;

        $billingEligibleBalance = min(
            (int) $balance
                ->verified_billable_balance_minor,
            $depositOnlyProjection
        );

        $tier = $this->pricing->tierForBalance(
            $billingEligibleBalance
        );

        if (
            $tier === null
            || (int) $tier['full_monthly_fee_minor']
                <= (int) $cycle->active_full_fee_minor
        ) {
            /*
             * Withdrawals/losses and sub-minimum balance movement never create
             * a current-period refund, downgrade or negative adjustment.
             */
            return null;
        }

        if ($eventAt->gte(CarbonImmutable::instance($cycle->period_end))) {
            throw new DomainException(
                'This balance belongs to the next billing period and cannot be processed as a current-period deposit upgrade.'
            );
        }

        $ratio = $this->calendar->ratio(
            $account,
            $eventAt,
            CarbonImmutable::instance($cycle->period_start),
            CarbonImmutable::instance($cycle->period_end)
        );

        $charge = $this->proration->upgradeCharge(
            (int) $tier['full_monthly_fee_minor'],
            (int) $cycle->active_full_fee_minor,
            (int) $ratio['numerator'],
            (int) $ratio['denominator']
        );

        if ($charge <= 0) {
            return null;
        }

        return DB::transaction(
            function () use (
                $account,
                $cycle,
                $balance,
                $tier,
                $eventAt,
                $ratio,
                $charge,
                $admin,
                $balanceEvent,
                $billingEligibleBalance,
                $cumulativeDeposits
            ): CopyTradeBillingInvoice {
                $lockedCycle = CopyTradeBillingCycle::query()
                    ->whereKey($cycle->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $tier['full_monthly_fee_minor']
                    <= (int) $lockedCycle->active_full_fee_minor
                ) {
                    $existing = CopyTradeBillingInvoice::query()
                        ->where(
                            'billing_cycle_id',
                            $lockedCycle->getKey()
                        )
                        ->where(
                            'invoice_type',
                            CopyTradeBillingInvoice::TYPE_UPGRADE
                        )
                        ->latest('id')
                        ->first();

                    if ($existing) {
                        return $existing;
                    }

                    throw new DomainException(
                        'This tier upgrade has already been applied.'
                    );
                }

                $snapshot = $this->lockSnapshot(
                    $account,
                    $lockedCycle,
                    $balance,
                    CopyTradeBillingSnapshot::PURPOSE_UPGRADE,
                    $eventAt,
                    $tier,
                    $ratio,
                    $balanceEvent,
                    $billingEligibleBalance
                );

                $productCode = CopyTradeProductCode::monthly(
                    (string) $account
                        ->brokerMarket
                        ->market_category,
                    (int) $account
                        ->accountBenefit
                        ->account_slot
                );

                $invoice = $this->invoices->issue(
                    $account,
                    $lockedCycle,
                    $snapshot,
                    CopyTradeBillingInvoice::TYPE_UPGRADE,
                    $productCode,
                    $charge,
                    'TIER_UPGRADE',
                    'Prorated Copy Trade higher-tier external-deposit upgrade',
                    [
                        'old_full_fee_minor' =>
                            (int) $lockedCycle
                                ->active_full_fee_minor,
                        'new_full_fee_minor' =>
                            (int) $tier[
                                'full_monthly_fee_minor'
                            ],
                        'ratio_numerator' =>
                            (int) $ratio['numerator'],
                        'ratio_denominator' =>
                            (int) $ratio['denominator'],
                        'formula_code' =>
                            (string) $ratio[
                                'formula_code'
                            ],
                        'external_deposit_event_uuid' =>
                            $balanceEvent->uuid,
                        'external_deposit_amount_minor' =>
                            (int) $balanceEvent
                                ->deposit_amount_minor,
                        'cumulative_external_deposits_minor' =>
                            $cumulativeDeposits,
                        'billing_eligible_balance_minor' =>
                            $billingEligibleBalance,
                    ],
                    $eventAt
                );

                $lockedCycle->forceFill([
                    'active_tier_code' =>
                        $tier['code'],
                    'active_full_fee_minor' =>
                        $tier[
                            'full_monthly_fee_minor'
                        ],
                ])->save();

                AuditLog::record(
                    actorType:
                        $admin ? 'ADMIN' : 'SYSTEM',
                    actorId:
                        $admin?->getKey(),
                    action:
                        'COPY_TRADE_BILLING_TIER_UPGRADE',
                    module: 'COPY_TRADING',
                    targetType:
                        'COPY_TRADE_BILLING_CYCLE',
                    targetId:
                        $lockedCycle->getKey(),
                    newValues: [
                        'invoice_uuid' =>
                            $invoice->uuid,
                        'new_tier_code' =>
                            $tier['code'],
                        'new_full_fee_minor' =>
                            $tier[
                                'full_monthly_fee_minor'
                            ],
                        'upgrade_charge_minor' =>
                            $charge,
                    ]
                );

                return $invoice;
            },
            3
        );
    }

    public function sweepAccount(
        CopyTradeBrokerAccount $account,
        ?CarbonImmutable $at = null
    ): array {
        $at ??= CarbonImmutable::now();

        $account->loadMissing([
            'brokerMarket',
            'accountBenefit',
            'entitlementPeriods',
        ]);

        if (
            ! in_array(
                $account->status,
                [
                    CopyTradeBrokerAccount::STATUS_ACTIVE,
                    CopyTradeBrokerAccount::STATUS_SUSPENDED,
                ],
                true
            )
        ) {
            return [
                'action' =>
                    'SKIPPED_ACCOUNT_STATE',
            ];
        }

        if (
            $account
                ->accountBenefit
                ?->benefit_type
            === CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1
        ) {
            return [
                'action' =>
                    'LIFETIME_FREE',
            ];
        }

        $latestCycle = CopyTradeBillingCycle::query()
            ->where(
                'broker_account_id',
                $account->getKey()
            )
            ->latest('period_end')
            ->first();

        /*
         * A historical reversed payment remains dispute evidence, but it must
         * not automatically suspend a customer who has already entered a later
         * billing cycle with valid payment evidence. Automatic suspension is
         * limited to the latest/current billing cycle.
         */
        $reversedInvoice =
            $latestCycle
                ? CopyTradeBillingInvoice::query()
                    ->with('payment')
                    ->where(
                        'broker_account_id',
                        $account->getKey()
                    )
                    ->where(
                        'billing_cycle_id',
                        $latestCycle->getKey()
                    )
                    ->where(
                        'status',
                        CopyTradeBillingInvoice::STATUS_PAID
                    )
                    ->whereHas(
                        'payment',
                        fn ($query) =>
                            $query->where(
                                'status',
                                \App\Models\Payment::STATUS_PAYMENT_REVERSED
                            )
                    )
                    ->latest('paid_at')
                    ->first()
                : null;

        if ($reversedInvoice) {
            $this->payments
                ->reconcileReversedPayment(
                    $reversedInvoice
                );

            return [
                'action' =>
                    'PAYMENT_REVERSAL_SUSPENDED',
                'invoice_uuid' =>
                    $reversedInvoice->uuid,
            ];
        }

        if (! $latestCycle) {
            $invoice = $this->initializeAccountBilling(
                $account
            );

            return [
                'action' =>
                    $invoice
                        ? 'INITIAL_INVOICE_ISSUED'
                        : 'BILLING_INITIALIZED',
                'invoice_uuid' =>
                    $invoice?->uuid,
            ];
        }

        $openInvoice = CopyTradeBillingInvoice::query()
            ->where(
                'billing_cycle_id',
                $latestCycle->getKey()
            )
            ->where(
                'status',
                CopyTradeBillingInvoice::STATUS_OPEN
            )
            ->oldest('issued_at')
            ->first();

        if ($openInvoice) {
            return $this->enforceGrace(
                $account,
                $latestCycle,
                $openInvoice,
                $at
            );
        }

        if (
            $latestCycle->status
            === CopyTradeBillingCycle::STATUS_FREE
            && $at->gte(
                CarbonImmutable::instance(
                    $latestCycle->period_end
                )
            )
        ) {
            $invoice = $this->issueInitialCharge(
                $account,
                CarbonImmutable::instance(
                    $latestCycle->period_end
                ),
                CopyTradeProductCode::monthly(
                    (string) $account
                        ->brokerMarket
                        ->market_category,
                    (int) $account
                        ->accountBenefit
                        ->account_slot
                )
            );

            return [
                'action' =>
                    'FREE_PERIOD_ENDED_INVOICE_ISSUED',
                'invoice_uuid' =>
                    $invoice->uuid,
            ];
        }

        if (
            $latestCycle->status
            === CopyTradeBillingCycle::STATUS_PAID
            && $at->gte(
                CarbonImmutable::instance(
                    $latestCycle->period_end
                )
            )
        ) {
            $invoice = $this->issueRenewal(
                $account,
                $latestCycle
            );

            return [
                'action' =>
                    $invoice
                        ? 'RENEWAL_INVOICE_ISSUED'
                        : 'RENEWAL_INELIGIBLE',
                'invoice_uuid' =>
                    $invoice?->uuid,
            ];
        }

        return [
            'action' =>
                'NO_ACTION',
        ];
    }

    public function sweepAll(
        ?CarbonImmutable $at = null
    ): array {
        $at ??= CarbonImmutable::now();

        $summary = [
            'processed' => 0,
            'actions' => [],
        ];

        CopyTradeBrokerAccount::query()
            ->whereIn('status', [
                CopyTradeBrokerAccount::STATUS_ACTIVE,
                CopyTradeBrokerAccount::STATUS_SUSPENDED,
            ])
            ->orderBy('id')
            ->chunkById(
                100,
                function ($accounts) use (
                    &$summary,
                    $at
                ): void {
                    foreach ($accounts as $account) {
                        $result = $this->sweepAccount(
                            $account,
                            $at
                        );

                        $summary['processed']++;

                        $key = (string) (
                            $result['action']
                            ?? 'UNKNOWN'
                        );

                        $summary['actions'][$key] =
                            (
                                $summary[
                                    'actions'
                                ][$key]
                                ?? 0
                            ) + 1;
                    }
                }
            );

        return $summary;
    }

    private function initializeVerifiedAccount(
        CopyTradeBrokerAccount $account
    ): ?CopyTradeBillingInvoice {
        $preview = $this->benefits->preview(
            $account
        );

        if (
            ! (bool) (
                $preview['benefit_resolved']
                ?? false
            )
        ) {
            throw new DomainException(
                'Copy Trade benefit must be resolvable before billing.'
            );
        }

        $benefitType = (string) (
            $preview['benefit_type']
            ?? ''
        );

        if (
            in_array(
                $benefitType,
                [
                    CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
                    CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
                ],
                true
            )
        ) {
            return null;
        }

        if (
            (bool) (
                $preview['entitlement_passed']
                ?? false
            )
        ) {
            /*
             * The customer already has the cleared paid entitlement.
             * Do not create a second activation charge.
             */
            return null;
        }

        $productCode = trim(
            (string) (
                $preview['product_code']
                ?? ''
            )
        );

        if ($productCode === '') {
            throw new DomainException(
                'Copy Trade paid product code could not be resolved.'
            );
        }

        return $this->issueInitialCharge(
            $account,
            CarbonImmutable::now(),
            $productCode
        );
    }

    private function issueInitialCharge(
        CopyTradeBrokerAccount $account,
        CarbonImmutable $eventAt,
        string $productCode
    ): CopyTradeBillingInvoice {
        $account->loadMissing('brokerMarket');

        $balance = $this->balanceAtOrBefore(
            $account,
            $eventAt
        );

        $tier = $this->pricing->requireEligibleTier(
            (int) $balance
                ->verified_billable_balance_minor
        );

        $period = $this->calendar->periodFor(
            $account,
            $eventAt
        );

        $periodStart =
            CarbonImmutable::instance(
                $period['period_start']
            );

        $periodEnd =
            CarbonImmutable::instance(
                $period['period_end']
            );

        $ratio = $this->calendar->ratio(
            $account,
            $eventAt,
            $periodStart,
            $periodEnd
        );

        $charge = $this->proration->prorate(
            (int) $tier[
                'full_monthly_fee_minor'
            ],
            (int) $ratio['numerator'],
            (int) $ratio['denominator']
        );

        if ($charge <= 0) {
            throw new DomainException(
                'The calculated Copy Trade activation charge is zero because no billable period remains.'
            );
        }

        return DB::transaction(
            function () use (
                $account,
                $balance,
                $tier,
                $period,
                $ratio,
                $charge,
                $eventAt,
                $productCode,
                $periodStart,
                $periodEnd
            ): CopyTradeBillingInvoice {
                $cycle =
                    CopyTradeBillingCycle::query()
                        ->firstOrCreate(
                            [
                                'broker_account_id' =>
                                    $account->getKey(),
                                'period_start' =>
                                    $periodStart,
                                'period_end' =>
                                    $periodEnd,
                            ],
                            [
                                'market_category' =>
                                    $account
                                        ->brokerMarket
                                        ->market_category,
                                'cycle_basis' =>
                                    $period['basis'],
                                'effective_start_at' =>
                                    $eventAt,
                                'currency' => 'USD',
                                'active_tier_code' =>
                                    $tier['code'],
                                'active_full_fee_minor' =>
                                    $tier[
                                        'full_monthly_fee_minor'
                                    ],
                                'status' =>
                                    CopyTradeBillingCycle::STATUS_OPEN,
                            ]
                        );

                $snapshot = $this->lockSnapshot(
                    $account,
                    $cycle,
                    $balance,
                    CopyTradeBillingSnapshot::PURPOSE_INITIAL,
                    $eventAt,
                    $tier,
                    $ratio
                );

                if (
                    strtoupper(
                        (string) $account
                            ->brokerMarket
                            ->market_category
                    ) === 'FOREX'
                ) {
                    $this->calendar
                        ->lockForexCalendar(
                            $periodStart,
                            $periodEnd
                        );
                }

                return $this->invoices->issue(
                    $account,
                    $cycle,
                    $snapshot,
                    CopyTradeBillingInvoice::TYPE_INITIAL,
                    $productCode,
                    $charge,
                    'FIRST_PERIOD',
                    'Prorated first Copy Trade billing period',
                    [
                        'full_monthly_fee_minor' =>
                            $tier[
                                'full_monthly_fee_minor'
                            ],
                        'ratio_numerator' =>
                            $ratio['numerator'],
                        'ratio_denominator' =>
                            $ratio['denominator'],
                        'formula_code' =>
                            $ratio['formula_code'],
                    ],
                    $eventAt
                );
            },
            3
        );
    }

    private function issueRenewal(
        CopyTradeBrokerAccount $account,
        CopyTradeBillingCycle $previousCycle
    ): ?CopyTradeBillingInvoice {
        $account->loadMissing([
            'brokerMarket',
            'accountBenefit',
        ]);

        $periodEnd = CarbonImmutable::instance(
            $previousCycle->period_end
        );

        $balance = $this->balanceAtOrBefore(
            $account,
            $periodEnd
        );

        $tier = $this->pricing->tierForBalance(
            (int) $balance
                ->verified_billable_balance_minor
        );

        $minimum = (int) config(
            'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
            25000
        );

        if (
            (int) $balance
                ->verified_billable_balance_minor
                < $minimum
            || $tier === null
        ) {
            DB::transaction(
                function () use (
                    $previousCycle,
                    $account
                ): void {
                    $lockedPrevious =
                        CopyTradeBillingCycle::query()
                            ->whereKey(
                                $previousCycle->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $lockedPrevious->forceFill([
                        'status' =>
                            CopyTradeBillingCycle::STATUS_INELIGIBLE,
                        'closed_at' => now(),
                    ])->save();

                    $this->suspendAccount(
                        $account,
                        'COPY_TRADE_BILLING_BELOW_MINIMUM'
                    );
                },
                3
            );

            return null;
        }

        $nextPeriod = $this->calendar->periodFor(
            $account,
            $periodEnd->addSecond()
        );

        $ratio = [
            'numerator' => 1,
            'denominator' => 1,
            'formula_code' => 'FULL_PERIOD',
        ];

        return DB::transaction(
            function () use (
                $account,
                $previousCycle,
                $periodEnd,
                $balance,
                $tier,
                $nextPeriod,
                $ratio
            ): CopyTradeBillingInvoice {
                $lockedPrevious =
                    CopyTradeBillingCycle::query()
                        ->whereKey(
                            $previousCycle->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $cycle =
                    CopyTradeBillingCycle::query()
                        ->firstOrCreate(
                            [
                                'broker_account_id' =>
                                    $account->getKey(),
                                'period_start' =>
                                    $nextPeriod[
                                        'period_start'
                                    ],
                                'period_end' =>
                                    $nextPeriod[
                                        'period_end'
                                    ],
                            ],
                            [
                                'market_category' =>
                                    $account
                                        ->brokerMarket
                                        ->market_category,
                                'cycle_basis' =>
                                    $nextPeriod['basis'],
                                'effective_start_at' =>
                                    $nextPeriod[
                                        'period_start'
                                    ],
                                'currency' => 'USD',
                                'active_tier_code' =>
                                    $tier['code'],
                                'active_full_fee_minor' =>
                                    $tier[
                                        'full_monthly_fee_minor'
                                    ],
                                'status' =>
                                    CopyTradeBillingCycle::STATUS_OPEN,
                            ]
                        );

                $snapshot = $this->lockSnapshot(
                    $account,
                    $cycle,
                    $balance,
                    CopyTradeBillingSnapshot::PURPOSE_RENEWAL,
                    $periodEnd,
                    $tier,
                    $ratio
                );

                if (
                    strtoupper(
                        (string) $account
                            ->brokerMarket
                            ->market_category
                    ) === 'FOREX'
                ) {
                    $this->calendar
                        ->lockForexCalendar(
                            CarbonImmutable::instance(
                                $cycle->period_start
                            ),
                            CarbonImmutable::instance(
                                $cycle->period_end
                            )
                        );
                }

                $productCode =
                    CopyTradeProductCode::monthly(
                        (string) $account
                            ->brokerMarket
                            ->market_category,
                        (int) $account
                            ->accountBenefit
                            ->account_slot
                    );

                $invoice = $this->invoices->issue(
                    $account,
                    $cycle,
                    $snapshot,
                    CopyTradeBillingInvoice::TYPE_RENEWAL,
                    $productCode,
                    (int) $tier[
                        'full_monthly_fee_minor'
                    ],
                    'MONTHLY_RENEWAL',
                    'Copy Trade monthly renewal',
                    [
                        'full_monthly_fee_minor' =>
                            $tier[
                                'full_monthly_fee_minor'
                            ],
                        'locked_period_end_balance_minor' =>
                            $balance
                                ->verified_billable_balance_minor,
                    ],
                    $periodEnd
                );

                $lockedPrevious->forceFill([
                    'status' =>
                        CopyTradeBillingCycle::STATUS_CLOSED,
                    'closed_at' => now(),
                ])->save();

                return $invoice;
            },
            3
        );
    }

    private function enforceGrace(
        CopyTradeBrokerAccount $account,
        CopyTradeBillingCycle $cycle,
        CopyTradeBillingInvoice $invoice,
        CarbonImmutable $at
    ): array {
        if (
            $invoice->due_at
            && $at->lt(
                CarbonImmutable::instance(
                    $invoice->due_at
                )
            )
        ) {
            return [
                'action' =>
                    'WAITING_FOR_DUE_DATE',
            ];
        }

        $graceDays = config(
            'tradex_copy_trading.billing.grace_period_days'
        );

        if ($graceDays === null) {
            return [
                'action' =>
                    'GRACE_CONFIGURATION_REQUIRED',
            ];
        }

        $graceDays = max(
            0,
            (int) $graceDays
        );

        if ($cycle->grace_started_at === null) {
            $graceStart =
                $invoice->due_at
                    ? CarbonImmutable::instance(
                        $invoice->due_at
                    )
                    : $at;

            $cycle->forceFill([
                'status' =>
                    CopyTradeBillingCycle::STATUS_GRACE,
                'grace_started_at' =>
                    $graceStart,
                'grace_expires_at' =>
                    $graceStart
                        ->addDays(
                            $graceDays
                        ),
            ])->save();

            $cycle->refresh();
        }

        if (
            $cycle->grace_expires_at
            && $at->gte(
                CarbonImmutable::instance(
                    $cycle->grace_expires_at
                )
            )
        ) {
            $cycle->forceFill([
                'status' =>
                    CopyTradeBillingCycle::STATUS_SUSPENDED,
            ])->save();

            $this->suspendAccount(
                $account,
                'COPY_TRADE_BILLING_UNPAID'
            );

            return [
                'action' =>
                    'SUSPENDED_AFTER_GRACE',
            ];
        }

        return [
            'action' => 'IN_GRACE',
            'grace_expires_at' =>
                $cycle
                    ->grace_expires_at
                    ?->toIso8601String(),
        ];
    }

    private function ensureFreeCycle(
        CopyTradeBrokerAccount $account,
        CarbonImmutable $start,
        CarbonImmutable $end
    ): CopyTradeBillingCycle {
        return CopyTradeBillingCycle::query()
            ->firstOrCreate(
                [
                    'broker_account_id' =>
                        $account->getKey(),
                    'period_start' => $start,
                    'period_end' => $end,
                ],
                [
                    'market_category' =>
                        $account
                            ->brokerMarket
                            ->market_category,
                    'cycle_basis' =>
                        'THIRTY_DAY_FREE',
                    'effective_start_at' =>
                        $start,
                    'currency' => 'USD',
                    'active_tier_code' => null,
                    'active_full_fee_minor' => 0,
                    'status' =>
                        CopyTradeBillingCycle::STATUS_FREE,
                ]
            );
    }

    private function bootstrapLegacyPaidCycle(
        CopyTradeBrokerAccount $account
    ): CopyTradeBillingCycle {
        $account->loadMissing([
            'brokerMarket',
            'accountBenefit',
            'entitlementPeriods.userEntitlement.payment',
        ]);

        $paidPeriod =
            $account->entitlementPeriods
                ->first(
                    fn (
                        CopyTradeEntitlementPeriod $period
                    ): bool =>
                        $period->entitlement_type
                        === CopyTradeEntitlementPeriod::TYPE_PAID
                        && $period
                            ->userEntitlement
                            ?->payment
                            ?->isCleared()
                );

        if (! $paidPeriod) {
            throw new DomainException(
                'Active paid Copy Trade account is missing its cleared paid entitlement evidence.'
            );
        }

        $eventAt = CarbonImmutable::instance(
            $account->activated_at
            ?? $paidPeriod->starts_at
            ?? now()
        );

        $balance = $this->balanceAtOrBefore(
            $account,
            $eventAt
        );

        $tier = $this->pricing->requireEligibleTier(
            (int) $balance
                ->verified_billable_balance_minor
        );

        $period = $this->calendar->periodFor(
            $account,
            $eventAt
        );

        $cycle = CopyTradeBillingCycle::query()
            ->firstOrCreate(
                [
                    'broker_account_id' =>
                        $account->getKey(),
                    'period_start' =>
                        $period['period_start'],
                    'period_end' =>
                        $period['period_end'],
                ],
                [
                    'market_category' =>
                        $account
                            ->brokerMarket
                            ->market_category,
                    'cycle_basis' =>
                        $period['basis'],
                    'effective_start_at' =>
                        $eventAt,
                    'currency' => 'USD',
                    'active_tier_code' =>
                        $tier['code'],
                    'active_full_fee_minor' =>
                        $tier[
                            'full_monthly_fee_minor'
                        ],
                    'status' =>
                        CopyTradeBillingCycle::STATUS_PAID,
                ]
            );

        $this->lockSnapshot(
            $account,
            $cycle,
            $balance,
            CopyTradeBillingSnapshot::PURPOSE_BASELINE,
            $eventAt,
            $tier,
            [
                'numerator' => 1,
                'denominator' => 1,
                'formula_code' =>
                    'LEGACY_PAID_BASELINE',
            ]
        );

        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action:
                'COPY_TRADE_BILLING_LEGACY_PAID_CYCLE_BOOTSTRAPPED',
            module: 'COPY_TRADING',
            targetType:
                'COPY_TRADE_BILLING_CYCLE',
            targetId:
                $cycle->getKey(),
            newValues: [
                'broker_account_uuid' =>
                    $account->uuid,
                'cycle_uuid' =>
                    $cycle->uuid,
                'paid_entitlement_uuid' =>
                    $paidPeriod
                        ->userEntitlement
                        ?->uuid,
                'no_new_charge' => true,
            ]
        );

        return $cycle;
    }

    private function lockSnapshot(
        CopyTradeBrokerAccount $account,
        CopyTradeBillingCycle $cycle,
        CopyTradeVerifiedBalanceSnapshot $balance,
        string $purpose,
        CarbonImmutable $eventAt,
        array $tier,
        array $ratio,
        ?CopyTradeBillingBalanceEvent $balanceEvent = null,
        ?int $billingEligibleBalanceMinor = null
    ): CopyTradeBillingSnapshot {
        $billingEligibleBalanceMinor ??=
            (int) $balance
                ->verified_billable_balance_minor;

        $key = hash(
            'sha256',
            implode('|', [
                $account->getKey(),
                $cycle->getKey(),
                $balance->uuid,
                $balanceEvent?->uuid ?? 'NO_BALANCE_EVENT',
                $billingEligibleBalanceMinor,
                $purpose,
                $eventAt->toIso8601String(),
                $tier['code'],
                $ratio['numerator'],
                $ratio['denominator'],
            ])
        );

        return CopyTradeBillingSnapshot::query()
            ->firstOrCreate(
                [
                    'idempotency_key_hash' =>
                        $key,
                ],
                [
                    'broker_account_id' =>
                        $account->getKey(),
                    'billing_cycle_id' =>
                        $cycle->getKey(),
                    'source_balance_snapshot_id' =>
                        $balance->getKey(),
                    'billing_balance_event_id' =>
                        $balanceEvent?->getKey(),
                    'purpose' => $purpose,
                    'market_category' =>
                        $account
                            ->brokerMarket
                            ->market_category,
                    'currency' => 'USD',
                    'verified_billable_balance_minor' =>
                        $balance
                            ->verified_billable_balance_minor,
                    'billing_eligible_balance_minor' =>
                        $billingEligibleBalanceMinor,
                    'tier_code' =>
                        $tier['code'],
                    'full_monthly_fee_minor' =>
                        $tier[
                            'full_monthly_fee_minor'
                        ],
                    'event_at' =>
                        $eventAt,
                    'period_start' =>
                        $cycle->period_start,
                    'period_end' =>
                        $cycle->period_end,
                    'ratio_numerator' =>
                        $ratio['numerator'],
                    'ratio_denominator' =>
                        $ratio['denominator'],
                    'formula_code' =>
                        $ratio['formula_code'],
                    'calculation_inputs' => [
                        'source_balance_snapshot_uuid' =>
                            $balance->uuid,
                        'balance_source' =>
                            $balance->source,
                        'billing_balance_event_uuid' =>
                            $balanceEvent?->uuid,
                        'external_deposit_amount_minor' =>
                            $balanceEvent
                                ?->deposit_amount_minor,
                        'billing_eligible_balance_minor' =>
                            $billingEligibleBalanceMinor,
                        'tier_code' =>
                            $tier['code'],
                        'full_monthly_fee_minor' =>
                            $tier[
                                'full_monthly_fee_minor'
                            ],
                        'ratio_numerator' =>
                            $ratio['numerator'],
                        'ratio_denominator' =>
                            $ratio['denominator'],
                    ],
                    'locked_at' => now(),
                ]
            );
    }

    private function latestBalance(
        CopyTradeBrokerAccount $account
    ): CopyTradeVerifiedBalanceSnapshot {
        $snapshot =
            CopyTradeVerifiedBalanceSnapshot::query()
                ->where(
                    'broker_account_id',
                    $account->getKey()
                )
                ->latest('observed_at')
                ->latest('id')
                ->first();

        if (! $snapshot) {
            throw new DomainException(
                'Verified Billable Balance snapshot is required for billing.'
            );
        }

        return $snapshot;
    }

    private function balanceAtOrBefore(
        CopyTradeBrokerAccount $account,
        CarbonImmutable $at
    ): CopyTradeVerifiedBalanceSnapshot {
        $snapshot =
            CopyTradeVerifiedBalanceSnapshot::query()
                ->where(
                    'broker_account_id',
                    $account->getKey()
                )
                ->where(
                    'observed_at',
                    '<=',
                    $at
                )
                ->latest('observed_at')
                ->latest('id')
                ->first();

        if (! $snapshot) {
            throw new DomainException(
                'A Verified Billable Balance snapshot at or before the billing event is required.'
            );
        }

        return $snapshot;
    }

    private function suspendAccount(
        CopyTradeBrokerAccount $account,
        string $reason
    ): void {
        $locked = CopyTradeBrokerAccount::query()
            ->whereKey($account->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (
            $locked->status
            !== CopyTradeBrokerAccount::STATUS_SUSPENDED
        ) {
            $locked->forceFill([
                'status' =>
                    CopyTradeBrokerAccount::STATUS_SUSPENDED,
                'suspended_at' => now(),
                'last_status_reason' =>
                    $reason,
            ])->save();

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action:
                    'COPY_TRADE_BILLING_SUSPENDED_NEW_TRADES',
                module: 'COPY_TRADING',
                targetType:
                    'COPY_TRADE_BROKER_ACCOUNT',
                targetId:
                    $locked->getKey(),
                newValues: [
                    'reason_code' =>
                        $reason,
                    'existing_trade_safety_unchanged' =>
                        true,
                ]
            );
        }
    }
}
