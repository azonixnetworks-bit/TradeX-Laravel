<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\CopyTrading\CopyTradeVerifiedBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeActivationUiApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    public function test_activation_routes_are_registered(): void
    {
        $this->assertTrue(
            app('router')
                ->getRoutes()
                ->hasNamedRoute(
                    'admin.copy_trading.accounts.activation.show'
                )
        );

        $this->assertTrue(
            app('router')
                ->getRoutes()
                ->hasNamedRoute(
                    'admin.copy_trading.accounts.activation.balance.store'
                )
        );

        $this->assertTrue(
            app('router')
                ->getRoutes()
                ->hasNamedRoute(
                    'admin.copy_trading.accounts.activation.evaluate'
                )
        );

        $this->assertTrue(
            app('router')
                ->getRoutes()
                ->hasNamedRoute(
                    'api.v1.copy-trade.broker-accounts.activation.show'
                )
        );
    }

    public function test_view_permission_can_open_activation_page_but_cannot_mutate(): void
    {
        $user = $this->createApiUser();
        $account = $this->verifiedAccount(
            $user,
            '210000001'
        );

        $admin = $this->adminWithPermissions([
            'copy_trading.accounts.view',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        );

        $this->get(
            route(
                'admin.copy_trading.accounts.activation.show',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )->assertOk();

        $this->post(
            route(
                'admin.copy_trading.accounts.activation.balance.store',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            ),
            [
                'verified_billable_balance_usd' =>
                    '250.00',
            ]
        )->assertForbidden();

        $this->post(
            route(
                'admin.copy_trading.accounts.activation.evaluate',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )->assertForbidden();
    }

    public function test_activation_admin_can_record_audited_manual_balance_snapshot(): void
    {
        $user = $this->createApiUser();
        $account = $this->verifiedAccount(
            $user,
            '210000002'
        );

        $admin = $this->adminWithPermissions([
            'copy_trading.accounts.view',
            'copy_trading.accounts.activate',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        );

        $response = $this->post(
            route(
                'admin.copy_trading.accounts.activation.balance.store',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            ),
            [
                'verified_billable_balance_usd' =>
                    '250.75',
                'source_reference' =>
                    'partner-portal-balance-case-1',
            ]
        );

        $response->assertRedirect();

        $this->assertDatabaseHas(
            'copy_trade_verified_balance_snapshots',
            [
                'broker_account_id' =>
                    $account->id,
                'currency' =>
                    'USD',
                'verified_billable_balance_minor' =>
                    25075,
                'source' =>
                    'ADMIN_MANUAL',
                'recorded_by_admin_id' =>
                    $admin->id,
            ]
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'ADMIN',
                'actor_id' =>
                    $admin->id,
                'action' =>
                    'COPY_TRADE_VERIFIED_BILLABLE_BALANCE_RECORDED',
                'module' =>
                    'COPY_TRADING',
                'target_id' =>
                    $account->id,
            ]
        );
    }

    public function test_admin_ui_activates_only_after_every_engine_gate_passes(): void
    {
        $user = $this->createApiUser();

        $this->createClearedEducationEntitlement(
            $user
        );

        $account = $this->verifiedAccount(
            $user,
            '210000003'
        );

        $admin = $this->adminWithPermissions([
            'copy_trading.accounts.view',
            'copy_trading.accounts.activate',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        );

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            24999,
            'USD',
            'ADMIN_MANUAL',
            null,
            $admin
        );

        $this->post(
            route(
                'admin.copy_trading.accounts.activation.evaluate',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )
            ->assertRedirect()
            ->assertSessionHasErrors(
                'activation'
            );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_VERIFIED,
            $account->fresh()->status
        );

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'ADMIN_MANUAL',
            null,
            $admin
        );

        $this->post(
            route(
                'admin.copy_trading.accounts.activation.evaluate',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )
            ->assertRedirect()
            ->assertSessionHas(
                'success'
            );

        $this->assertSame(
            CopyTradeBrokerAccount::STATUS_ACTIVE,
            $account->fresh()->status
        );

        $this->assertNotNull(
            $account->fresh()->activated_at
        );
    }

    public function test_customer_activation_endpoint_is_owner_only(): void
    {
        $owner = $this->createApiUser();
        $other = $this->createApiUser();

        $account = $this->verifiedAccount(
            $owner,
            '210000004'
        );

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'SYSTEM_IMPORT'
        );

        $ownerToken =
            $this->issueBoundToken(
                $owner
            );

        $this->withToken(
            $ownerToken
        )
            ->getJson(
                route(
                    'api.v1.copy-trade.broker-accounts.activation.show',
                    [
                        'copyTradeBrokerAccount' =>
                            $account,
                    ]
                )
            )
            ->assertOk()
            ->assertJsonPath(
                'data.broker_account_uuid',
                $account->uuid
            );

        $otherToken =
            $this->issueBoundToken(
                $other
            );

        $this->withToken(
            $otherToken
        )
            ->getJson(
                route(
                    'api.v1.copy-trade.broker-accounts.activation.show',
                    [
                        'copyTradeBrokerAccount' =>
                            $account,
                    ]
                )
            )
            ->assertNotFound();
    }

    public function test_customer_activation_payload_never_exposes_internal_balance_reference_or_gate_evidence(): void
    {
        $user = $this->createApiUser();
        $account = $this->verifiedAccount(
            $user,
            '210000005'
        );

        app(
            CopyTradeVerifiedBalanceService::class
        )->record(
            $account,
            25000,
            'USD',
            'SYSTEM_IMPORT',
            'super-secret-broker-reference'
        );

        $token = $this->issueBoundToken(
            $user
        );

        $response = $this->withToken(
            $token
        )
            ->getJson(
                route(
                    'api.v1.copy-trade.broker-accounts.activation.show',
                    [
                        'copyTradeBrokerAccount' =>
                            $account,
                    ]
                )
            )
            ->assertOk();

        $json = $response->getContent();

        $this->assertStringNotContainsString(
            'super-secret-broker-reference',
            $json
        );

        $this->assertStringNotContainsString(
            'source_reference',
            $json
        );

        $this->assertStringNotContainsString(
            'source_reference_hash',
            $json
        );

        $this->assertStringNotContainsString(
            'recorded_by_admin_id',
            $json
        );

        $this->assertStringNotContainsString(
            '"evidence"',
            $json
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

    private function createClearedEducationEntitlement(
        User $user
    ): UserEntitlement {
        $order = PaymentOrder::query()
            ->create([
                'user_id' =>
                    $user->id,
                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
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
                    now()->subDay(),
            ]);

        $reference =
            'ui-api-capture-'.Str::uuid();

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
                    now()->subDay(),
            ]);

        return UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->id,
                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    now()->subDay(),
                'payment_id' =>
                    $payment->id,
            ]);
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()->toString(),
                'name' =>
                    'Activation UI Test Admin',
                'email' =>
                    'activation-ui-admin-'
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
                    'ACT_UI_'
                    .Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Activation UI Test Role',
                'description' =>
                    'ADMIN-23C test role.',
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

        return $admin->fresh(
            'roles.permissions'
        );
    }
}
