<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBroker;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeBrokerMarket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CopyTradeBillingUiApiSecurityTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_billing_routes_are_registered(): void
    {
        foreach ([
            'admin.copy_trading.billing.index',
            'admin.copy_trading.billing.calendar.index',
            'admin.copy_trading.billing.accounts.show',
            'admin.copy_trading.billing.accounts.initialize',
            'admin.copy_trading.billing.accounts.upgrade',
            'admin.copy_trading.billing.accounts.sweep',
            'admin.copy_trading.billing.accounts.adjustments.store',
            'admin.copy_trading.billing.invoices.link-payment',
            'api.v1.copy-trade.billing.index',
            'api.v1.copy-trade.broker-accounts.billing.show',
            'api.v1.copy-trade.billing.invoices.show',
        ] as $name) {
            $this->assertTrue(
                app('router')
                    ->getRoutes()
                    ->hasNamedRoute($name),
                'Missing route: '.$name
            );
        }
    }

    public function test_customer_account_billing_endpoint_is_owner_only(): void
    {
        $owner = $this->createApiUser();
        $other = $this->createApiUser();

        $account = $this->accountFor(
            $owner,
            '710000001'
        );

        $ownerToken =
            $this->issueBoundToken(
                $owner
            );

        $this->withToken($ownerToken)
            ->getJson(
                route(
                    'api.v1.copy-trade.broker-accounts.billing.show',
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

        $this->withToken($otherToken)
            ->getJson(
                route(
                    'api.v1.copy-trade.broker-accounts.billing.show',
                    [
                        'copyTradeBrokerAccount' =>
                            $account,
                    ]
                )
            )
            ->assertNotFound();
    }

    public function test_customer_invoice_endpoint_is_owner_only_and_hides_internal_ids_and_evidence(): void
    {
        $owner = $this->createApiUser();
        $other = $this->createApiUser();

        $account = $this->accountFor(
            $owner,
            '710000002'
        );

        $invoice =
            CopyTradeBillingInvoice::query()
                ->create([
                    'idempotency_key_hash' =>
                        hash(
                            'sha256',
                            (string) Str::uuid()
                        ),
                    'user_id' =>
                        $owner->id,
                    'broker_account_id' =>
                        $account->id,
                    'billing_cycle_id' =>
                        null,
                    'billing_snapshot_id' =>
                        null,
                    'invoice_type' =>
                        CopyTradeBillingInvoice::TYPE_RENEWAL,
                    'product_code' =>
                        'COPY_TRADING_CRYPTO_ACCOUNT_1_MONTHLY',
                    'currency' =>
                        'USD',
                    'subtotal_minor' =>
                        500,
                    'total_minor' =>
                        500,
                    'status' =>
                        CopyTradeBillingInvoice::STATUS_OPEN,
                    'period_start' =>
                        now()->startOfMonth(),
                    'period_end' =>
                        now()
                            ->startOfMonth()
                            ->addMonth(),
                    'issued_at' =>
                        now(),
                    'due_at' =>
                        now(),
                ]);

        $ownerToken =
            $this->issueBoundToken(
                $owner
            );

        $response =
            $this->withToken(
                $ownerToken
            )
                ->getJson(
                    route(
                        'api.v1.copy-trade.billing.invoices.show',
                        [
                            'copyTradeBillingInvoice' =>
                                $invoice,
                        ]
                    )
                )
                ->assertOk();

        $payload = $response->getContent();

        foreach ([
            'payment_id',
            'payment_order_id',
            'source_reference',
            'source_reference_hash',
            'recorded_by_admin_id',
            'calculation_inputs',
            'evidence',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $payload
            );
        }

        $otherToken =
            $this->issueBoundToken(
                $other
            );

        $this->withToken($otherToken)
            ->getJson(
                route(
                    'api.v1.copy-trade.billing.invoices.show',
                    [
                        'copyTradeBillingInvoice' =>
                            $invoice,
                    ]
                )
            )
            ->assertNotFound();
    }

    public function test_view_only_admin_cannot_mutate_billing(): void
    {
        $user = $this->createApiUser();

        $account = $this->accountFor(
            $user,
            '710000003'
        );

        $admin = $this->adminWithPermissions([
            'copy_trading.billing.view',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        );

        $this->get(
            route(
                'admin.copy_trading.billing.accounts.show',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )->assertOk();

        $this->post(
            route(
                'admin.copy_trading.billing.accounts.initialize',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            )
        )->assertForbidden();

        $this->post(
            route(
                'admin.copy_trading.billing.accounts.adjustments.store',
                [
                    'copyTradeBrokerAccount' =>
                        $account,
                ]
            ),
            [
                'adjustment_type' =>
                    'CORRECTION_EVIDENCE',
                'amount_minor' =>
                    0,
                'reason' =>
                    'This is a test correction evidence reason.',
            ]
        )->assertForbidden();
    }

    private function accountFor(
        User $user,
        string $number
    ): CopyTradeBrokerAccount {
        $broker = CopyTradeBroker::query()
            ->create([
                'code' =>
                    'S'.Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Security Test Broker',
                'is_active' => true,
            ]);

        $market = CopyTradeBrokerMarket::query()
            ->create([
                'broker_id' =>
                    $broker->id,
                'market_category' =>
                    'CRYPTO',
                'referral_url' =>
                    'https://broker.example.test/security',
                'required_currency' =>
                    'USD',
                'live_account_only' =>
                    true,
                'is_active' =>
                    true,
            ]);

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
                        (string) Str::uuid()
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
                    now()->subDay(),
                'submitted_at' =>
                    now()->subDays(2),
                'verified_at' =>
                    now()->subDay(),
                'activated_at' =>
                    now(),
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
                    'Billing Security Admin',
                'email' =>
                    'billing-security-'
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
                    'BILL_SEC_'
                    .Str::upper(
                        Str::random(8)
                    ),
                'name' =>
                    'Billing Security Role',
                'description' =>
                    'ADMIN-24 security test role.',
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
                            'code' => $code,
                        ],
                        [
                            'name' => $code,
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
            ->sync($permissionIds);

        $admin->roles()
            ->sync([$role->id]);

        return $admin->fresh(
            'roles.permissions'
        );
    }
}
