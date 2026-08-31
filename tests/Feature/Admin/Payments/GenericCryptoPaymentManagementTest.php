<?php

namespace Tests\Feature\Admin\Payments;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CryptoDeposit;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\PaymentReceivingAccount;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class GenericCryptoPaymentManagementTest
    extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_authorized_admin_can_review_proof_and_approve_generic_crypto_into_central_premium_entitlement(): void
    {
        Storage::fake('local');

        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user =
            $this->createApiUser([
                'country_code' =>
                    'LK',
                'account_type' =>
                    'FREE',
            ]);

        $account =
            PaymentReceivingAccount::query()
                ->create([
                    'type' =>
                        'CRYPTO',
                    'display_name' =>
                        'BTC Main',
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'BTC',
                    'tender_amount_minor' =>
                        100000,
                    'amount_scale' =>
                        8,
                    'coin_name' =>
                        'Bitcoin',
                    'coin_symbol' =>
                        'BTC',
                    'network' =>
                        'BITCOIN',
                    'wallet_address' =>
                        'bc1qtradexapprovalwallet123456',
                    'is_active' =>
                        true,
                    'display_order' =>
                        1,
                ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $prepared =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23b2-admin-approval-001'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $account->uuid,
                    ]
                )
                ->assertCreated();

        $orderUuid =
            (string) $prepared
                ->json(
                    'data.uuid'
                );

        $this->withToken(
            $token
        )
            ->post(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        'btc_admin_review_tx_1234567890',
                    'amount' =>
                        '0.00100000',
                    'proof_file' =>
                        UploadedFile::fake()
                            ->create(
                                'btc-receipt.pdf',
                                64,
                                'application/pdf'
                            ),
                ],
                [
                    'Accept' =>
                        'application/json',
                ]
            )
            ->assertOk();

        $deposit =
            CryptoDeposit::query()
                ->firstOrFail();

        $admin =
            $this->adminWithPermissions([
                'payments.view',
                'payments.verify',
                'payments.reconcile',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.payments.crypto.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'BTC'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.payments.crypto.proof',
                    $deposit
                )
            )
            ->assertOk()
            ->assertHeader(
                'X-Content-Type-Options',
                'nosniff'
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.payments.crypto.approve',
                    $deposit
                ),
                [
                    'reason' =>
                        'Verified transaction on the configured Bitcoin network.',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.payments.crypto.index'
                )
            );

        $order =
            PaymentOrder::query()
                ->where(
                    'uuid',
                    $orderUuid
                )
                ->firstOrFail();

        $payment =
            Payment::query()
                ->where(
                    'payment_order_id',
                    $order->id
                )
                ->firstOrFail();

        $this->assertSame(
            PaymentOrder::STATUS_CLEARED,
            $order->status
        );

        $this->assertSame(
            Payment::STATUS_CLEARED,
            $payment->status
        );

        $this->assertSame(
            'PREMIUM',
            $user->fresh()
                ->account_type
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' =>
                    $user->id,
                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'payment_id' =>
                    $payment->id,
            ]
        );

        $this->assertDatabaseHas(
            'crypto_deposits',
            [
                'id' =>
                    $deposit->id,
                'status' =>
                    CryptoDeposit::STATUS_APPROVED,
                'reviewed_by_admin_id' =>
                    $admin->id,
            ]
        );
    }

    public function test_rejected_generic_crypto_never_creates_payment_or_entitlement(): void
    {
        config()->set(
            'tradex_payments.crypto_manual.enabled',
            true
        );

        $user =
            $this->createApiUser([
                'country_code' =>
                    'IN',
                'account_type' =>
                    'FREE',
            ]);

        $account =
            PaymentReceivingAccount::query()
                ->create([
                    'type' =>
                        'CRYPTO',
                    'display_name' =>
                        'USDC Polygon',
                    'country_code' =>
                        'IN',
                    'currency' =>
                        'USDC',
                    'tender_amount_minor' =>
                        79000000,
                    'amount_scale' =>
                        6,
                    'coin_name' =>
                        'USD Coin',
                    'coin_symbol' =>
                        'USDC',
                    'network' =>
                        'POLYGON',
                    'wallet_address' =>
                        '0xTradeXPolygonWallet123456',
                    'is_active' =>
                        true,
                    'display_order' =>
                        1,
                ]);

        $token =
            $this->issueBoundToken(
                $user
            );

        $prepared =
            $this->withToken(
                $token
            )
                ->withHeader(
                    'Idempotency-Key',
                    'admin23b2-admin-reject-001'
                )
                ->postJson(
                    '/api/v1/payments/manual-orders',
                    [
                        'receiving_account_uuid' =>
                            $account->uuid,
                    ]
                )
                ->assertCreated();

        $orderUuid =
            (string) $prepared
                ->json(
                    'data.uuid'
                );

        $this->withToken(
            $token
        )
            ->postJson(
                '/api/v1/payments/manual-orders/'
                    .$orderUuid
                    .'/crypto-evidence',
                [
                    'tx_hash' =>
                        'polygon_reject_tx_123456789',
                    'amount' =>
                        '79.000000',
                ]
            )
            ->assertOk();

        $deposit =
            CryptoDeposit::query()
                ->firstOrFail();

        $admin =
            $this->adminWithPermissions([
                'payments.view',
                'payments.reject',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.payments.crypto.reject',
                    $deposit
                ),
                [
                    'reason' =>
                        'Transaction could not be verified on the configured network.',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.payments.crypto.index'
                )
            );

        $this->assertDatabaseCount(
            'payments',
            0
        );

        $this->assertDatabaseCount(
            'user_entitlements',
            0
        );

        $this->assertSame(
            'FREE',
            $user->fresh()
                ->account_type
        );

        $this->assertDatabaseHas(
            'payment_orders',
            [
                'uuid' =>
                    $orderUuid,
                'status' =>
                    PaymentOrder::STATUS_FAILED,
            ]
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),
                    'name' =>
                        'ADMIN Generic Crypto Test',
                    'email' =>
                        'admin-crypto-'
                        .Str::lower(
                            Str::random(12)
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
                    'locked_until' =>
                        null,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'CRYPTO_'
                        .strtoupper(
                            Str::random(8)
                        ),
                    'name' =>
                        'Generic Crypto Test Role',
                    'is_system' =>
                        false,
                    'is_active' =>
                        true,
                ]);

        $admin->roles()
            ->attach(
                $role->id
            );

        foreach (
            $codes
            as $code
        ) {
            $permission =
                AdminPermission::query()
                    ->firstOrCreate(
                        [
                            'code' =>
                                $code,
                        ],
                        [
                            'name' =>
                                $code,
                            'module' =>
                                'PAYMENTS',
                        ]
                    );

            $role->permissions()
                ->attach(
                    $permission->id
                );
        }

        return $admin->fresh();
    }
}
