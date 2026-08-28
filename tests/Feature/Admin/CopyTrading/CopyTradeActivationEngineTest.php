<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeActivationEvaluation;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\CopyTradeEntitlementPeriod;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\CopyTrading\CopyTradeActivationService;
use App\Services\CopyTrading\CopyTradeProductCode;
use App\Services\CopyTrading\CopyTradeVerifiedBalanceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeActivationEngineTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set(
            'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
            25000
        );
        config()->set(
            'tradex_copy_trading.activation.currency',
            'USD'
        );
        config()->set(
            'tradex_copy_trading.activation.first_eligible_users_lifetime_free_count',
            1000
        );
        config()->set(
            'tradex_copy_trading.activation.later_user_account_1_free_days',
            30
        );
        config()->set(
            'tradex_copy_trading.activation.balance_sources',
            [
                'ADMIN_MANUAL',
                'BROKER_API',
                'SYSTEM_IMPORT',
            ]
        );
        config()->set(
            'tradex_copy_trading.activation.trigger_sources',
            [
                'ADMIN',
                'SYSTEM',
                'BROKER_API',
            ]
        );
    }

    public function test_verified_account_below_250_usd_never_activates(): void
    {
        $user = $this->createApiUser();
        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subDay()
        );

        $account = $this->verifiedAccount($user, '100000001');

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $account,
                24999,
                'USD',
                'SYSTEM_IMPORT'
            );

        $result = app(CopyTradeActivationService::class)
            ->activate(
                $account,
                null,
                'SYSTEM'
            );

        $this->assertFalse($result['activated']);
        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_VERIFIED,
            $account->fresh()->status
        );
        $this->assertNull(
            $account->fresh()->activated_at
        );

        $evaluation = CopyTradeActivationEvaluation::query()
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(
            CopyTradeActivationEvaluation::STATUS_FAIL,
            $evaluation->overall_status
        );

        $this->assertDatabaseHas(
            'copy_trade_activation_evaluation_gates',
            [
                'activation_evaluation_id' =>
                    $evaluation->id,
                'gate_code' =>
                    CopyTradeActivationService::GATE_BALANCE,
                'passed' =>
                    0,
                'reason_code' =>
                    'VERIFIED_BILLABLE_BALANCE_BELOW_MINIMUM',
            ]
        );
    }

    public function test_first_cohort_account_one_gets_lifetime_free_and_activates(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-28 14:00:00')
        );

        $user = $this->createApiUser();

        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subDays(10)
        );

        $account = $this->verifiedAccount(
            $user,
            '100000002'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $account,
                25000,
                'USD',
                'BROKER_API',
                'balance-ref-1'
            );

        $result = app(CopyTradeActivationService::class)
            ->activate(
                $account,
                null,
                'SYSTEM'
            );

        $this->assertTrue($result['activated']);
        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_ACTIVE,
            $account->fresh()->status
        );

        $benefit = $account->fresh()
            ->accountBenefit()
            ->firstOrFail();

        $this->assertSame(1, $benefit->account_slot);
        $this->assertSame(1, $benefit->cohort_position);
        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
            $benefit->benefit_type
        );

        $period = $account->fresh()
            ->entitlementPeriods()
            ->firstOrFail();

        $this->assertSame(
            CopyTradeEntitlementPeriod::TYPE_LIFETIME_FREE,
            $period->entitlement_type
        );
        $this->assertNull($period->ends_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' =>
                'COPY_TRADE_BROKER_ACCOUNT_ACTIVATED',
            'module' =>
                'COPY_TRADING',
            'target_id' =>
                $account->id,
        ]);
    }

    public function test_later_cohort_account_one_gets_exactly_30_consecutive_days(): void
    {
        Carbon::setTestNow(
            Carbon::parse('2026-08-28 15:00:00')
        );

        config()->set(
            'tradex_copy_trading.activation.first_eligible_users_lifetime_free_count',
            1
        );

        $earlier = $this->createApiUser();
        $this->createClearedEntitlement(
            $earlier,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subDays(20)
        );

        $user = $this->createApiUser();
        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subDays(10)
        );

        $account = $this->verifiedAccount(
            $user,
            '100000003'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $account,
                30000,
                'USD',
                'SYSTEM_IMPORT'
            );

        $result = app(CopyTradeActivationService::class)
            ->activate(
                $account,
                null,
                'SYSTEM'
            );

        $this->assertTrue($result['activated']);

        $benefit = $account->fresh()
            ->accountBenefit()
            ->firstOrFail();

        $this->assertSame(2, $benefit->cohort_position);
        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
            $benefit->benefit_type
        );

        $period = $account->fresh()
            ->entitlementPeriods()
            ->firstOrFail();

        $this->assertSame(
            CopyTradeEntitlementPeriod::TYPE_THIRTY_DAY_FREE,
            $period->entitlement_type
        );
        $this->assertSame(
            now()->addDays(30)->toDateTimeString(),
            $period->ends_at?->toDateTimeString()
        );
    }

    public function test_account_two_requires_cleared_paid_monthly_entitlement(): void
    {
        $user = $this->createApiUser();

        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subMonth()
        );

        $first = $this->verifiedAccount(
            $user,
            '100000004'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $first,
                50000,
                'USD',
                'SYSTEM_IMPORT'
            );

        app(CopyTradeActivationService::class)
            ->activate(
                $first,
                null,
                'SYSTEM'
            );

        $second = $this->verifiedAccount(
            $user,
            '100000005'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $second,
                50000,
                'USD',
                'SYSTEM_IMPORT'
            );

        $failed = app(CopyTradeActivationService::class)
            ->activate(
                $second,
                null,
                'SYSTEM'
            );

        $this->assertFalse($failed['activated']);
        $this->assertSame(
            'PAID_REQUIRED',
            $failed['benefit']['benefit_type']
        );
        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_VERIFIED,
            $second->fresh()->status
        );

        $productCode =
            CopyTradeProductCode::monthly(
                'FOREX',
                2
            );

        $this->createClearedEntitlement(
            $user,
            $productCode,
            now()
        );

        $passed = app(CopyTradeActivationService::class)
            ->activate(
                $second,
                null,
                'SYSTEM'
            );

        $this->assertTrue($passed['activated']);

        $benefit = $second->fresh()
            ->accountBenefit()
            ->firstOrFail();

        $this->assertSame(2, $benefit->account_slot);
        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED,
            $benefit->benefit_type
        );

        $period = $second->fresh()
            ->entitlementPeriods()
            ->firstOrFail();

        $this->assertSame(
            CopyTradeEntitlementPeriod::TYPE_PAID,
            $period->entitlement_type
        );
        $this->assertSame(
            $productCode,
            $period->product_code
        );
        $this->assertNotNull(
            $period->user_entitlement_id
        );
    }

    public function test_disconnected_slot_can_be_reused_but_free_benefit_never_resets(): void
    {
        $user = $this->createApiUser();

        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subMonth()
        );

        $original = $this->verifiedAccount(
            $user,
            '100000006'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $original,
                60000,
                'USD',
                'SYSTEM_IMPORT'
            );

        app(CopyTradeActivationService::class)
            ->activate(
                $original,
                null,
                'SYSTEM'
            );

        $original->forceFill([
            'status' =>
                CopyTradeBrokerAccount::STATUS_DISCONNECTED,
            'disconnected_at' =>
                now(),
        ])->save();

        $replacement = $this->verifiedAccount(
            $user,
            '100000007'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $replacement,
                60000,
                'USD',
                'SYSTEM_IMPORT'
            );

        $failed = app(CopyTradeActivationService::class)
            ->activate(
                $replacement,
                null,
                'SYSTEM'
            );

        $this->assertFalse($failed['activated']);
        $this->assertSame(1, $failed['benefit']['account_slot']);
        $this->assertTrue(
            $failed['benefit']['previous_free_used']
        );
        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED,
            $failed['benefit']['benefit_type']
        );

        $this->createClearedEntitlement(
            $user,
            CopyTradeProductCode::monthly(
                'FOREX',
                1
            ),
            now()
        );

        $passed = app(CopyTradeActivationService::class)
            ->activate(
                $replacement,
                null,
                'SYSTEM'
            );

        $this->assertTrue($passed['activated']);

        $this->assertSame(
            2,
            CopyTradeAccountBenefit::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->where(
                    'market_category',
                    'FOREX'
                )
                ->where(
                    'account_slot',
                    1
                )
                ->count()
        );

        $this->assertSame(
            CopyTradeAccountBenefit::BENEFIT_PAID_REQUIRED,
            $replacement
                ->fresh()
                ->accountBenefit
                ->benefit_type
        );
    }

    public function test_manual_non_payment_education_entitlement_does_not_consume_frozen_cohort(): void
    {
        $user = $this->createApiUser();

        UserEntitlement::query()->create([
            'user_id' =>
                $user->id,
            'product_code' =>
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'status' =>
                UserEntitlement::STATUS_ACTIVE,
            'activated_at' =>
                now(),
            'payment_id' =>
                null,
        ]);

        $account = $this->verifiedAccount(
            $user,
            '100000008'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $account,
                25000,
                'USD',
                'SYSTEM_IMPORT'
            );

        $result = app(CopyTradeActivationService::class)
            ->activate(
                $account,
                null,
                'SYSTEM'
            );

        $this->assertFalse($result['activated']);
        $this->assertNull(
            $result['benefit']['cohort_position']
        );
        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_VERIFIED,
            $account->fresh()->status
        );
    }

    public function test_admin_activation_requires_dedicated_permission(): void
    {
        $user = $this->createApiUser();
        $account = $this->verifiedAccount(
            $user,
            '100000009'
        );

        $admin = $this->adminWithPermissions([
            'copy_trading.accounts.view',
        ]);

        $this->expectException(
            AuthorizationException::class
        );

        app(CopyTradeActivationService::class)
            ->activate(
                $account,
                $admin,
                'ADMIN'
            );
    }

    public function test_activation_is_idempotent_after_account_is_active(): void
    {
        $user = $this->createApiUser();

        $this->createClearedEntitlement(
            $user,
            PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            now()->subDay()
        );

        $account = $this->verifiedAccount(
            $user,
            '100000010'
        );

        app(CopyTradeVerifiedBalanceService::class)
            ->record(
                $account,
                25000,
                'USD',
                'SYSTEM_IMPORT'
            );

        $service = app(
            CopyTradeActivationService::class
        );

        $first = $service->activate(
            $account,
            null,
            'SYSTEM'
        );

        $evaluationCount =
            CopyTradeActivationEvaluation::query()
                ->where(
                    'broker_account_id',
                    $account->id
                )
                ->count();

        $second = $service->activate(
            $account->fresh(),
            null,
            'SYSTEM'
        );

        $this->assertTrue($first['activated']);
        $this->assertTrue($second['activated']);
        $this->assertTrue($second['already_active']);

        $this->assertSame(
            $evaluationCount,
            CopyTradeActivationEvaluation::query()
                ->where(
                    'broker_account_id',
                    $account->id
                )
                ->count()
        );
    }

    private function verifiedAccount(
        User $user,
        string $number
    ): CopyTradeBrokerAccount {
        [$broker, $market] =
            $this->brokerMarket();

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
                    substr(
                        $number,
                        -4
                    ),
                'status' =>
                    CopyTradeBrokerAccount::STATUS_VERIFIED,
                'resubmission_count' =>
                    0,
                'verified_currency' =>
                    'USD',
                'verified_account_mode' =>
                    'LIVE',
                'partner_attribution_confirmed_at' =>
                    now(),
                'submitted_at' =>
                    now()->subMinute(),
                'verified_at' =>
                    now(),
            ]);
    }

    private function brokerMarket(): array
    {
        $broker = CopyTradeBroker::query()
            ->firstOrCreate(
                [
                    'code' =>
                        'IC_MARKETS',
                ],
                [
                    'name' =>
                        'IC Markets',
                    'is_active' =>
                        true,
                ]
            );

        $market = CopyTradeBrokerMarket::query()
            ->firstOrCreate(
                [
                    'broker_id' =>
                        $broker->id,
                    'market_category' =>
                        'FOREX',
                ],
                [
                    'referral_url' =>
                        'https://broker.example.test/tradex-partner',
                    'required_currency' =>
                        'USD',
                    'live_account_only' =>
                        true,
                    'is_active' =>
                        true,
                ]
            );

        return [
            $broker->fresh(),
            $market->fresh('broker'),
        ];
    }

    private function createClearedEntitlement(
        User $user,
        string $productCode,
        Carbon $activatedAt
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
                    7900,
                'status' =>
                    PaymentOrder::STATUS_CLEARED,
                'client_idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'cleared_at' =>
                    $activatedAt,
            ]);

        $reference =
            'test-capture-'.Str::uuid();

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
                    7900,
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
                    $activatedAt,
            ]);

        return UserEntitlement::query()
            ->updateOrCreate(
                [
                    'user_id' =>
                        $user->id,
                    'product_code' =>
                        $productCode,
                ],
                [
                    'status' =>
                        UserEntitlement::STATUS_ACTIVE,
                    'activated_at' =>
                        $activatedAt,
                    'payment_id' =>
                        $payment->id,
                    'revoked_at' =>
                        null,
                    'revoke_reason' =>
                        null,
                ]
            );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),
                'name' =>
                    'Activation Test Admin',
                'email' =>
                    'activation-admin-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@tradex.test',
                'email_verified_at' =>
                    now(),
                'password' =>
                    'TestingPassword123!',
                'is_active' =>
                    true,
                'failed_login_attempts' =>
                    0,
            ]);

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'ACTIVATION_TEST_'
                    .Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Activation Test Role',
                'description' =>
                    'ADMIN-23 activation test role.',
                'is_system' =>
                    false,
                'is_active' =>
                    true,
            ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->updateOrCreate(
                        [
                            'code' =>
                                $code,
                        ],
                        [
                            'name' =>
                                $code,
                            'module' =>
                                'COPY_TRADING',
                            'description' =>
                                null,
                        ]
                    );

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()
            ->sync(
                $permissionIds
            );

        $admin->roles()
            ->sync([
                $role->id,
            ]);

        return $admin->fresh('roles');
    }
}
