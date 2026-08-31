<?php

namespace Tests\Feature\Admin\Payments;

use App\Models\AdminUser;
use App\Models\BankDeposit;
use App\Models\CryptoDeposit;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Admin\Payments\AdminPaymentService;
use App\Services\Admin\Payments\GenericCryptoPaymentService;
use App\Services\Entitlements\ClearedPaymentEntitlementReconciler;
use App\Services\Payments\PaymentMoney;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ManualPaymentEntitlementCentralizationTest
    extends TestCase
{
    use RefreshDatabase;

    public function test_admin_payment_service_has_no_direct_premium_entitlement_write_path(): void
    {
        $source =
            (string) file_get_contents(
                app_path(
                    'Services/Admin/Payments/AdminPaymentService.php'
                )
            );

        $this->assertStringContainsString(
            'ClearedPaymentEntitlementReconciler',
            $source
        );

        $this->assertStringNotContainsString(
            'UserEntitlement::query()',
            $source
        );

        $this->assertStringNotContainsString(
            'clearOrderAndActivateEntitlement',
            $source
        );

        $this->assertStringNotContainsString(
            "'PREMIUM_ENTITLEMENT_ACTIVATED'",
            $source
        );
    }

    public function test_bank_manual_approval_rolls_back_if_central_entitlement_reconciliation_fails(): void
    {
        $user =
            $this->freeUser(
                'BANKCENTRAL'
            );

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $user->id,
                    'product_code' =>
                        PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                    'provider' =>
                        PaymentOrder::PROVIDER_LOCAL_BANK,
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'LKR',
                    'amount_minor' =>
                        2500000,
                    'status' =>
                        PaymentOrder::STATUS_PENDING_REVIEW,
                    'client_idempotency_key_hash' =>
                        hash(
                            'sha256',
                            Str::uuid()
                                ->toString()
                        ),
                ]);

        $deposit =
            BankDeposit::query()
                ->create([
                    'payment_order_id' =>
                        $order->id,
                    'user_id' =>
                        $user->id,
                    'depositor_name' =>
                        'Centralization Test',
                    'bank_reference' =>
                        'CENTRAL-BANK-'
                        .Str::upper(
                            Str::random(
                                10
                            )
                        ),
                    'deposit_date' =>
                        now()
                            ->toDateString(),
                    'proof_disk' =>
                        'local',
                    'proof_path' =>
                        'payments/bank/'
                        .$user->uuid
                        .'/centralization.pdf',
                    'status' =>
                        BankDeposit::STATUS_SUBMITTED,
                    'submitted_at' =>
                        now(),
                ]);

        $admin =
            $this->admin();

        $reconciler =
            Mockery::mock(
                ClearedPaymentEntitlementReconciler::class
            );

        $reconciler
            ->shouldReceive(
                'reconcile'
            )
            ->once()
            ->andThrow(
                new DomainException(
                    'Forced central entitlement failure.'
                )
            );

        $service =
            new AdminPaymentService(
                app(
                    PaymentMoney::class
                ),
                $reconciler
            );

        try {
            $service
                ->approveBankDeposit(
                    $deposit,
                    $admin,
                    'Verified bank statement for atomic rollback test.',
                    Request::create(
                        '/admin/payments/test',
                        'PATCH'
                    )
                );

            $this->fail(
                'Central reconciliation failure should have aborted bank approval.'
            );
        } catch (
            DomainException $exception
        ) {
            $this->assertSame(
                'Forced central entitlement failure.',
                $exception
                    ->getMessage()
            );
        }

        $this->assertDatabaseHas(
            'payment_orders',
            [
                'id' =>
                    $order->id,
                'status' =>
                    PaymentOrder::STATUS_PENDING_REVIEW,
                'cleared_at' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'bank_deposits',
            [
                'id' =>
                    $deposit->id,
                'status' =>
                    BankDeposit::STATUS_SUBMITTED,
                'reviewed_by_admin_id' =>
                    null,
            ]
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
    }

    public function test_generic_crypto_manual_approval_rolls_back_if_central_entitlement_reconciliation_fails(): void
    {
        $user =
            $this->freeUser(
                'CRYPTOCENTRAL'
            );

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $user->id,
                    'product_code' =>
                        PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                    'provider' =>
                        PaymentOrder::PROVIDER_CRYPTO_MANUAL,
                    'country_code' =>
                        'LK',
                    'currency' =>
                        'USD',
                    'amount_minor' =>
                        7900,
                    'status' =>
                        PaymentOrder::STATUS_PENDING_REVIEW,
                    'client_idempotency_key_hash' =>
                        hash(
                            'sha256',
                            Str::uuid()
                                ->toString()
                        ),
                    'manual_reference' =>
                        'TXP-'
                        .Str::upper(
                            Str::random(
                                12
                            )
                        ),
                    'receiving_account_snapshot' => [
                        'coin_symbol' =>
                            'BTC',
                        'network' =>
                            'BITCOIN',
                        'amount' =>
                            '0.00100000',
                        'amount_scale' =>
                            8,
                    ],
                ]);

        $txHash =
            'atomic_crypto_tx_'
            .Str::lower(
                Str::random(
                    24
                )
            );

        $txIdentity =
            preg_match(
                '/^(?:0x)?[A-Fa-f0-9]{32,191}$/',
                $txHash
            ) === 1
                ? strtolower(
                    $txHash
                )
                : $txHash;

        $deposit =
            CryptoDeposit::query()
                ->create([
                    'payment_order_id' =>
                        $order->id,
                    'user_id' =>
                        $user->id,
                    'coin_symbol' =>
                        'BTC',
                    'network' =>
                        'BITCOIN',
                    'tx_hash' =>
                        $txHash,
                    'tx_hash_digest' =>
                        hash(
                            'sha256',
                            "BITCOIN\0"
                            .$txIdentity
                        ),
                    'amount_decimal' =>
                        '0.00100000',
                    'amount_scale' =>
                        8,
                    'status' =>
                        CryptoDeposit::STATUS_SUBMITTED,
                    'submitted_at' =>
                        now(),
                ]);

        $admin =
            $this->admin();

        $reconciler =
            Mockery::mock(
                ClearedPaymentEntitlementReconciler::class
            );

        $reconciler
            ->shouldReceive(
                'reconcile'
            )
            ->once()
            ->andThrow(
                new DomainException(
                    'Forced central entitlement failure.'
                )
            );

        $service =
            new GenericCryptoPaymentService(
                $reconciler
            );

        try {
            $service
                ->approve(
                    $deposit,
                    $admin,
                    'Verified blockchain transaction for atomic rollback test.',
                    Request::create(
                        '/admin/payments/test',
                        'PATCH'
                    )
                );

            $this->fail(
                'Central reconciliation failure should have aborted crypto approval.'
            );
        } catch (
            DomainException $exception
        ) {
            $this->assertSame(
                'Forced central entitlement failure.',
                $exception
                    ->getMessage()
            );
        }

        $this->assertDatabaseHas(
            'payment_orders',
            [
                'id' =>
                    $order->id,
                'status' =>
                    PaymentOrder::STATUS_PENDING_REVIEW,
                'cleared_at' =>
                    null,
            ]
        );

        $this->assertDatabaseHas(
            'crypto_deposits',
            [
                'id' =>
                    $deposit->id,
                'status' =>
                    CryptoDeposit::STATUS_SUBMITTED,
                'reviewed_by_admin_id' =>
                    null,
            ]
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
    }

    private function freeUser(
        string $referralCode
    ): User {
        return User::factory()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),
                'name' =>
                    'User '
                    .$referralCode,
                'email' =>
                    strtolower(
                        $referralCode
                    )
                    .'@tradex.test',
                'whatsapp_number' =>
                    '+947'
                    .random_int(
                        10000000,
                        99999999
                    ),
                'whatsapp_verified_at' =>
                    now(),
                'referral_code' =>
                    $referralCode,
                'country_code' =>
                    'LK',
                'language_code' =>
                    'en',
                'account_type' =>
                    'FREE',
                'status' =>
                    'ACTIVE',
                'premium_activated_at' =>
                    null,
            ]);
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),
                'name' =>
                    'ADMIN Centralization Test',
                'email' =>
                    'admin-central-'
                    .Str::lower(
                        Str::random(
                            12
                        )
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
    }
}
