<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeBillingCycle;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\CopyTradeEntitlementPeriod;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\CopyTrading\Billing\CopyTradeBillingBalanceEventService;
use App\Services\CopyTrading\Billing\CopyTradeBillingEngine;
use App\Services\CopyTrading\Billing\CopyTradeBillingPaymentService;
use App\Services\CopyTrading\CopyTradeActivationService;
use App\Services\CopyTrading\CopyTradeProductCode;
use App\Services\CopyTrading\CopyTradeVerifiedBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeBillingEngineTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_copy_trading.billing.grace_period_days',
            3
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_paid_account_activation_invoice_payment_then_admin23_activation_flow(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        [$user, $broker, $market, $education] =
            $this->qualifiedUserAndCryptoMarket();

        $this->createSlotOneLifetimeAccount(
            $user,
            $broker,
            $market,
            $education
        );

        $account = $this->verifiedAccount(
            $user,
            $broker,
            $market,
            '610000002'
        );

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()
        );

        $invoice = app(
            CopyTradeBillingEngine::class
        )->initializeAccountBilling(
            $account
        );

        $this->assertInstanceOf(
            CopyTradeBillingInvoice::class,
            $invoice
        );

        $this->assertSame(
            CopyTradeBillingInvoice::TYPE_INITIAL,
            $invoice->invoice_type
        );

        $this->assertSame(
            258,
            $invoice->total_minor
        );

        $this->assertSame(
            CopyTradeProductCode::monthly(
                'CRYPTO',
                2
            ),
            $invoice->product_code
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_VERIFIED,
            $account->fresh()->status
        );

        $payment = $this->clearedPaymentForInvoice(
            $invoice
        );

        app(
            CopyTradeBillingPaymentService::class
        )->linkClearedPayment(
            $invoice,
            $payment
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' => $user->id,
                'product_code' =>
                    CopyTradeProductCode::monthly(
                        'CRYPTO',
                        2
                    ),
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'payment_id' =>
                    $payment->id,
            ]
        );

        $activation = app(
            CopyTradeActivationService::class
        )->activate(
            $account->fresh(),
            null,
            'SYSTEM'
        );

        $this->assertTrue(
            $activation['activated']
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_ACTIVE,
            $account->fresh()->status
        );

        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED,
            $account
                ->fresh()
                ->accountBenefit
                ->benefit_type
        );

        $this->assertSame(
            CopyTradeBillingCycle::STATUS_PAID,
            $invoice
                ->fresh()
                ->cycle
                ->status
        );
    }

    public function test_thirty_day_free_account_bills_only_after_exact_free_period(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-01 12:00:00',
                'UTC'
            )
        );

        [$user, $broker, $market, $education] =
            $this->qualifiedUserAndCryptoMarket();

        $account = $this->activeAccount(
            $user,
            $broker,
            $market,
            '610000003',
            now()
        );

        $benefit =
            CopyTradeAccountBenefit::query()
                ->create([
                    'user_id' => $user->id,
                    'broker_account_id' =>
                        $account->id,
                    'education_entitlement_id' =>
                        $education->id,
                    'market_category' =>
                        'CRYPTO',
                    'account_slot' =>
                        1,
                    'cohort_position' =>
                        1001,
                    'benefit_type' =>
                        CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
                    'assigned_at' =>
                        now(),
                ]);

        CopyTradeEntitlementPeriod::query()
            ->create([
                'broker_account_id' =>
                    $account->id,
                'account_benefit_id' =>
                    $benefit->id,
                'user_entitlement_id' =>
                    null,
                'entitlement_type' =>
                    CopyTradeEntitlementPeriod::TYPE_THIRTY_DAY_FREE,
                'product_code' =>
                    CopyTradeProductCode::thirtyDayFree(
                        'CRYPTO',
                        1
                    ),
                'starts_at' =>
                    now(),
                'ends_at' =>
                    now()->addDays(30),
            ]);

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()
        );

        $engine = app(
            CopyTradeBillingEngine::class
        );

        $this->assertNull(
            $engine->initializeAccountBilling(
                $account->fresh()
            )
        );

        $this->assertSame(
            0,
            CopyTradeBillingInvoice::query()
                ->count()
        );

        $freeCycle =
            CopyTradeBillingCycle::query()
                ->firstOrFail();

        $this->assertSame(
            CopyTradeBillingCycle::STATUS_FREE,
            $freeCycle->status
        );

        $after = Carbon::parse(
            '2026-08-31 12:00:01',
            'UTC'
        )->toImmutable();

        $result = $engine->sweepAccount(
            $account->fresh(),
            $after
        );

        $this->assertSame(
            'FREE_PERIOD_ENDED_INVOICE_ISSUED',
            $result['action']
        );

        $invoice =
            CopyTradeBillingInvoice::query()
                ->firstOrFail();

        $this->assertGreaterThan(
            0,
            $invoice->total_minor
        );
    }

    public function test_same_tier_deposit_is_zero_and_higher_tier_charges_only_prorated_difference(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        $account =
            $this->activePaidAccountWithCycle(
                25000,
                '610000004'
            );

        $engine = app(
            CopyTradeBillingEngine::class
        );

        $sameTierSnapshot = app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            90000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()->addHour()
        );

        $sameTierEvent = app(
            CopyTradeBillingBalanceEventService::class
        )->recordExternalDeposit(
            $account,
            $sameTierSnapshot,
            65000
        );

        $this->assertNull(
            $engine
                ->evaluateExternalDepositUpgrade(
                    $account->fresh(),
                    $sameTierEvent
                )
        );

        $higherTierSnapshot = app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            100000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()->addHours(2)
        );

        $higherTierEvent = app(
            CopyTradeBillingBalanceEventService::class
        )->recordExternalDeposit(
            $account,
            $higherTierSnapshot,
            10000
        );

        $invoice =
            $engine
                ->evaluateExternalDepositUpgrade(
                    $account->fresh(),
                    $higherTierEvent
                );

        $this->assertInstanceOf(
            CopyTradeBillingInvoice::class,
            $invoice
        );

        $this->assertSame(
            CopyTradeBillingInvoice::TYPE_UPGRADE,
            $invoice->invoice_type
        );

        $this->assertGreaterThan(
            0,
            $invoice->total_minor
        );

        $this->assertLessThan(
            500,
            $invoice->total_minor
        );

        $this->assertSame(
            100000,
            $invoice
                ->snapshot
                ->billing_eligible_balance_minor
        );

        $this->assertSame(
            1000,
            $account
                ->billingCycles()
                ->firstOrFail()
                ->active_full_fee_minor
        );
    }


    public function test_positive_trading_balance_movement_cannot_manufacture_mid_period_upgrade(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        $account =
            $this->activePaidAccountWithCycle(
                25000,
                '610000009'
            );

        $snapshot = app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            150000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()->addHour()
        );

        /*
         * Actual VBB is now much higher, but only USD 10 is explicitly
         * verified as an external deposit. Deposit-only projection remains
         * USD 260, so trading balance movement cannot force a higher tier.
         */
        $event = app(
            CopyTradeBillingBalanceEventService::class
        )->recordExternalDeposit(
            $account,
            $snapshot,
            1000
        );

        $invoice = app(
            CopyTradeBillingEngine::class
        )->evaluateExternalDepositUpgrade(
            $account->fresh(),
            $event
        );

        $this->assertNull($invoice);

        $this->assertSame(
            500,
            $account
                ->billingCycles()
                ->firstOrFail()
                ->active_full_fee_minor
        );
    }

    public function test_renewal_uses_locked_period_end_balance_then_unpaid_account_suspends_after_configured_grace(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        $account =
            $this->activePaidAccountWithCycle(
                25000,
                '610000005'
            );

        $cycle = $account
            ->billingCycles()
            ->firstOrFail();

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            100000,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            Carbon::parse(
                '2026-08-31 23:59:59',
                'UTC'
            )
        );

        $engine = app(
            CopyTradeBillingEngine::class
        );

        $renewalAt = Carbon::parse(
            '2026-09-01 00:00:01',
            'UTC'
        )->toImmutable();

        $result = $engine->sweepAccount(
            $account->fresh(),
            $renewalAt
        );

        $this->assertSame(
            'RENEWAL_INVOICE_ISSUED',
            $result['action']
        );

        $renewal =
            CopyTradeBillingInvoice::query()
                ->where(
                    'invoice_type',
                    CopyTradeBillingInvoice::TYPE_RENEWAL
                )
                ->firstOrFail();

        $this->assertSame(
            1000,
            $renewal->total_minor
        );

        $this->assertSame(
            100000,
            $renewal
                ->snapshot
                ->verified_billable_balance_minor
        );

        $grace = $engine->sweepAccount(
            $account->fresh(),
            $renewalAt
        );

        $this->assertSame(
            'IN_GRACE',
            $grace['action']
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_ACTIVE,
            $account->fresh()->status
        );

        $suspended =
            $engine->sweepAccount(
                $account->fresh(),
                Carbon::parse(
                    '2026-09-04 00:00:02',
                    'UTC'
                )->toImmutable()
            );

        $this->assertSame(
            'SUSPENDED_AFTER_GRACE',
            $suspended['action']
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_SUSPENDED,
            $account->fresh()->status
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'COPY_TRADE_BILLING_SUSPENDED_NEW_TRADES',
                'target_id' =>
                    $account->id,
            ]
        );
    }

    public function test_lifetime_free_account_never_generates_invoice(): void
    {
        [$user, $broker, $market, $education] =
            $this->qualifiedUserAndCryptoMarket();

        $account = $this->activeAccount(
            $user,
            $broker,
            $market,
            '610000006',
            now()
        );

        $benefit =
            CopyTradeAccountBenefit::query()
                ->create([
                    'user_id' => $user->id,
                    'broker_account_id' =>
                        $account->id,
                    'education_entitlement_id' =>
                        $education->id,
                    'market_category' =>
                        'CRYPTO',
                    'account_slot' =>
                        1,
                    'cohort_position' =>
                        1,
                    'benefit_type' =>
                        CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
                    'assigned_at' =>
                        now(),
                ]);

        CopyTradeEntitlementPeriod::query()
            ->create([
                'broker_account_id' =>
                    $account->id,
                'account_benefit_id' =>
                    $benefit->id,
                'user_entitlement_id' =>
                    null,
                'entitlement_type' =>
                    CopyTradeEntitlementPeriod::TYPE_LIFETIME_FREE,
                'product_code' =>
                    CopyTradeProductCode::lifetimeFree(
                        'CRYPTO',
                        1
                    ),
                'starts_at' =>
                    now(),
                'ends_at' =>
                    null,
            ]);

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'SYSTEM_IMPORT'
        );

        $invoice = app(
            CopyTradeBillingEngine::class
        )->initializeAccountBilling(
            $account->fresh()
        );

        $this->assertNull($invoice);

        $this->assertSame(
            0,
            CopyTradeBillingInvoice::query()
                ->count()
        );
    }



    public function test_missing_grace_configuration_never_invents_a_duration_or_suspends_customer(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        config()->set(
            'tradex_copy_trading.billing.grace_period_days',
            null
        );

        $account =
            $this->activePaidAccountWithCycle(
                25000,
                '610000008'
            );

        $cycle =
            $account->billingCycles()
                ->firstOrFail();

        CopyTradeBillingInvoice::query()
            ->create([
                'idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'user_id' =>
                    $account->user_id,
                'broker_account_id' =>
                    $account->id,
                'billing_cycle_id' =>
                    $cycle->id,
                'billing_snapshot_id' =>
                    null,
                'invoice_type' =>
                    CopyTradeBillingInvoice::TYPE_UPGRADE,
                'product_code' =>
                    CopyTradeProductCode::monthly(
                        'CRYPTO',
                        2
                    ),
                'currency' =>
                    'USD',
                'subtotal_minor' =>
                    100,
                'total_minor' =>
                    100,
                'status' =>
                    CopyTradeBillingInvoice::STATUS_OPEN,
                'period_start' =>
                    $cycle->period_start,
                'period_end' =>
                    $cycle->period_end,
                'issued_at' =>
                    now(),
                'due_at' =>
                    now(),
            ]);

        $result = app(
            CopyTradeBillingEngine::class
        )->sweepAccount(
            $account->fresh(),
            now()->addDay()->toImmutable()
        );

        $this->assertSame(
            'GRACE_CONFIGURATION_REQUIRED',
            $result['action']
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_ACTIVE,
            $account->fresh()->status
        );
    }

    public function test_reversed_paid_invoice_suspends_new_copy_trades_and_revokes_that_payment_entitlement(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-08-16 00:00:00',
                'UTC'
            )
        );

        $account =
            $this->activePaidAccountWithCycle(
                25000,
                '610000007'
            );

        $cycle =
            $account->billingCycles()
                ->firstOrFail();

        $invoice =
            CopyTradeBillingInvoice::query()
                ->create([
                    'idempotency_key_hash' =>
                        hash(
                            'sha256',
                            (string) Str::uuid()
                        ),
                    'user_id' =>
                        $account->user_id,
                    'broker_account_id' =>
                        $account->id,
                    'billing_cycle_id' =>
                        $cycle->id,
                    'billing_snapshot_id' =>
                        null,
                    'invoice_type' =>
                        CopyTradeBillingInvoice::TYPE_RENEWAL,
                    'product_code' =>
                        CopyTradeProductCode::monthly(
                            'CRYPTO',
                            2
                        ),
                    'currency' =>
                        'USD',
                    'subtotal_minor' =>
                        500,
                    'total_minor' =>
                        500,
                    'status' =>
                        CopyTradeBillingInvoice::STATUS_OPEN,
                    'period_start' =>
                        $cycle->period_start,
                    'period_end' =>
                        $cycle->period_end,
                    'issued_at' =>
                        now(),
                    'due_at' =>
                        now(),
                ]);

        $payment =
            $this->clearedPaymentForInvoice(
                $invoice
            );

        app(
            CopyTradeBillingPaymentService::class
        )->linkClearedPayment(
            $invoice,
            $payment
        );

        $payment->forceFill([
            'status' =>
                Payment::STATUS_PAYMENT_REVERSED,
            'payment_reversed_at' =>
                now()->addHour(),
            'payment_reversal_code' =>
                Payment::REVERSAL_PAYPAL_PROCESSOR,
            'payment_reversal_reason' =>
                'Processor reversal test.',
        ])->save();

        $result = app(
            CopyTradeBillingEngine::class
        )->sweepAccount(
            $account->fresh(),
            now()->addHours(2)->toImmutable()
        );

        $this->assertSame(
            'PAYMENT_REVERSAL_SUSPENDED',
            $result['action']
        );

        $this->assertSame(
            CopyTradeBillingInvoice::STATUS_PAYMENT_REVERSED,
            $invoice->fresh()->status
        );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_SUSPENDED,
            $account->fresh()->status
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' =>
                    $account->user_id,
                'product_code' =>
                    $invoice->product_code,
                'status' =>
                    UserEntitlement::STATUS_REVOKED,
                'payment_id' =>
                    $payment->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'COPY_TRADE_BILLING_PAYMENT_REVERSED',
                'target_id' =>
                    $invoice->id,
            ]
        );
    }

    private function activePaidAccountWithCycle(
        int $balanceMinor,
        string $number
    ): CopyTradeBrokerAccount {
        [$user, $broker, $market, $education] =
            $this->qualifiedUserAndCryptoMarket();

        $account = $this->activeAccount(
            $user,
            $broker,
            $market,
            $number,
            now()
        );

        $monthly =
            $this->clearedEntitlement(
                $user,
                CopyTradeProductCode::monthly(
                    'CRYPTO',
                    2
                ),
                258,
                now()
            );

        $benefit =
            CopyTradeAccountBenefit::query()
                ->create([
                    'user_id' =>
                        $user->id,
                    'broker_account_id' =>
                        $account->id,
                    'education_entitlement_id' =>
                        $education->id,
                    'market_category' =>
                        'CRYPTO',
                    'account_slot' =>
                        2,
                    'cohort_position' =>
                        1,
                    'benefit_type' =>
                        CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED,
                    'assigned_at' =>
                        now(),
                ]);

        CopyTradeEntitlementPeriod::query()
            ->create([
                'broker_account_id' =>
                    $account->id,
                'account_benefit_id' =>
                    $benefit->id,
                'user_entitlement_id' =>
                    $monthly->id,
                'entitlement_type' =>
                    CopyTradeEntitlementPeriod::TYPE_PAID,
                'product_code' =>
                    CopyTradeProductCode::monthly(
                        'CRYPTO',
                        2
                    ),
                'starts_at' =>
                    now(),
                'ends_at' =>
                    null,
            ]);

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            $balanceMinor,
            'USD',
            'SYSTEM_IMPORT',
            null,
            null,
            now()
        );

        /*
         * Existing paid activation evidence predates ADMIN-24 billing invoice.
         * Initialization must bootstrap PAID without charging it again.
         */
        app(
            CopyTradeBillingEngine::class
        )->initializeAccountBilling(
            $account->fresh()
        );

        return $account->fresh([
            'billingCycles',
            'accountBenefit',
            'brokerMarket',
        ]);
    }

    private function qualifiedUserAndCryptoMarket(): array
    {
        $user = $this->createApiUser();

        $education = $this->clearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            7900,
            now()->subMonth()
        );

        $broker = CopyTradeBroker::query()
            ->create([
                'code' =>
                    'B'.Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Billing Test Broker',
                'is_active' => true,
            ]);

        $market = CopyTradeBrokerMarket::query()
            ->create([
                'broker_id' =>
                    $broker->id,
                'market_category' =>
                    'CRYPTO',
                'referral_url' =>
                    'https://broker.example.test/tradex',
                'required_currency' =>
                    'USD',
                'live_account_only' =>
                    true,
                'is_active' =>
                    true,
            ]);

        return [
            $user,
            $broker,
            $market,
            $education,
        ];
    }

    private function createSlotOneLifetimeAccount(
        User $user,
        CopyTradeBroker $broker,
        CopyTradeBrokerMarket $market,
        UserEntitlement $education
    ): void {
        $first = $this->activeAccount(
            $user,
            $broker,
            $market,
            '610000001',
            now()->subDay()
        );

        $benefit =
            CopyTradeAccountBenefit::query()
                ->create([
                    'user_id' =>
                        $user->id,
                    'broker_account_id' =>
                        $first->id,
                    'education_entitlement_id' =>
                        $education->id,
                    'market_category' =>
                        'CRYPTO',
                    'account_slot' =>
                        1,
                    'cohort_position' =>
                        1,
                    'benefit_type' =>
                        CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
                    'assigned_at' =>
                        now()->subDay(),
                ]);

        CopyTradeEntitlementPeriod::query()
            ->create([
                'broker_account_id' =>
                    $first->id,
                'account_benefit_id' =>
                    $benefit->id,
                'user_entitlement_id' =>
                    null,
                'entitlement_type' =>
                    CopyTradeEntitlementPeriod::TYPE_LIFETIME_FREE,
                'product_code' =>
                    CopyTradeProductCode::lifetimeFree(
                        'CRYPTO',
                        1
                    ),
                'starts_at' =>
                    now()->subDay(),
                'ends_at' =>
                    null,
            ]);
    }

    private function activeAccount(
        User $user,
        CopyTradeBroker $broker,
        CopyTradeBrokerMarket $market,
        string $number,
        Carbon $activatedAt
    ): CopyTradeBrokerAccount {
        return CopyTradeBrokerAccount::query()
            ->create([
                'user_id' =>
                    $user->id,
                'broker_id' =>
                    $broker->id,
                'broker_market_id' =>
                    $market->id,
                'account_number' =>
                    $number,
                'account_number_fingerprint' =>
                    hash(
                        'sha256',
                        $broker->id.'|'.$number
                    ),
                'account_number_last4' =>
                    substr($number, -4),
                'status' =>
                    CopyTradeBrokerAccount::STATUS_ACTIVE,
                'resubmission_count' =>
                    0,
                'verified_currency' =>
                    'USD',
                'verified_account_mode' =>
                    'LIVE',
                'partner_attribution_confirmed_at' =>
                    $activatedAt
                        ->copy()
                        ->subDay(),
                'submitted_at' =>
                    $activatedAt
                        ->copy()
                        ->subDays(2),
                'verified_at' =>
                    $activatedAt
                        ->copy()
                        ->subDay(),
                'activated_at' =>
                    $activatedAt,
            ]);
    }

    private function verifiedAccount(
        User $user,
        CopyTradeBroker $broker,
        CopyTradeBrokerMarket $market,
        string $number
    ): CopyTradeBrokerAccount {
        return CopyTradeBrokerAccount::query()
            ->create([
                'user_id' =>
                    $user->id,
                'broker_id' =>
                    $broker->id,
                'broker_market_id' =>
                    $market->id,
                'account_number' =>
                    $number,
                'account_number_fingerprint' =>
                    hash(
                        'sha256',
                        $broker->id.'|'.$number
                    ),
                'account_number_last4' =>
                    substr($number, -4),
                'status' =>
                    CopyTradeBrokerAccount::STATUS_VERIFIED,
                'resubmission_count' =>
                    0,
                'verified_currency' =>
                    'USD',
                'verified_account_mode' =>
                    'LIVE',
                'partner_attribution_confirmed_at' =>
                    now()->subDay(),
                'submitted_at' =>
                    now()->subDays(2),
                'verified_at' =>
                    now()->subDay(),
            ]);
    }

    private function clearedPaymentForInvoice(
        CopyTradeBillingInvoice $invoice
    ): Payment {
        $order = PaymentOrder::query()
            ->create([
                'user_id' =>
                    $invoice->user_id,
                'product_code' =>
                    $invoice->product_code,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'country_code' =>
                    'LK',
                'currency' =>
                    $invoice->currency,
                'amount_minor' =>
                    $invoice->total_minor,
                'status' =>
                    PaymentOrder::STATUS_CLEARED,
                'client_idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'cleared_at' =>
                    now(),
            ]);

        $reference =
            'billing-test-'.Str::uuid();

        return Payment::query()
            ->create([
                'payment_order_id' =>
                    $order->id,
                'user_id' =>
                    $invoice->user_id,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'currency' =>
                    $invoice->currency,
                'gross_amount_minor' =>
                    $invoice->total_minor,
                'provider_capture_reference_hash' =>
                    hash(
                        'sha256',
                        $reference
                    ),
                'provider_capture_reference' =>
                    $reference,
                'status' =>
                    Payment::STATUS_CLEARED,
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_NOT_APPLICABLE,
                'cleared_at' =>
                    now(),
            ]);
    }

    private function clearedEntitlement(
        User $user,
        string $productCode,
        int $amountMinor,
        Carbon $at
    ): UserEntitlement {
        $order = PaymentOrder::query()
            ->create([
                'user_id' =>
                    $user->id,
                'product_code' =>
                    $productCode,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'country_code' =>
                    'LK',
                'currency' =>
                    'USD',
                'amount_minor' =>
                    $amountMinor,
                'status' =>
                    PaymentOrder::STATUS_CLEARED,
                'client_idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'cleared_at' =>
                    $at,
            ]);

        $reference =
            'entitlement-test-'
            .Str::uuid();

        $payment = Payment::query()
            ->create([
                'payment_order_id' =>
                    $order->id,
                'user_id' =>
                    $user->id,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'currency' =>
                    'USD',
                'gross_amount_minor' =>
                    $amountMinor,
                'provider_capture_reference_hash' =>
                    hash(
                        'sha256',
                        $reference
                    ),
                'provider_capture_reference' =>
                    $reference,
                'status' =>
                    Payment::STATUS_CLEARED,
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_NOT_APPLICABLE,
                'cleared_at' =>
                    $at,
            ]);

        return UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->id,
                'product_code' =>
                    $productCode,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    $at,
                'payment_id' =>
                    $payment->id,
            ]);
    }
}
