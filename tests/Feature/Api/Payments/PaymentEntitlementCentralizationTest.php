<?php

namespace Tests\Feature\Api\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\Payments\OnlineGatewaySettlementService;
use App\Services\Payments\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PaymentEntitlementCentralizationTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_paypal_settlement_uses_central_entitlement_and_existing_payment_retry_reconciles_missing_entitlement(): void
    {
        $user = $this->createApiUser();
        $order = $this->paymentOrder(
            $user,
            PaymentOrder::PROVIDER_PAYPAL,
            'paypal-centralization-1'
        );

        $payload = [
            'id' => 'PAYPAL-CAPTURE-CENTRALIZATION-1',
            'status' => 'COMPLETED',
            'amount' => [
                'currency_code' => 'USD',
                'value' => '79.00',
            ],
        ];

        $service = app(PaymentSettlementService::class);

        $payment = $service->settlePayPalCapture(
            $order,
            $payload,
            'paypal-event-centralization-1'
        );

        $entitlement = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $this->assertTrue($payment->isCleared());
        $this->assertSame(
            $payment->getKey(),
            (int) $entitlement->payment_id
        );
        $this->assertSame(
            UserEntitlement::STATUS_ACTIVE,
            $entitlement->status
        );
        $this->assertSame(
            'PREMIUM',
            $user->fresh()->account_type
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' =>
                'EDUCATION_PREMIUM_ENTITLEMENT_ACTIVATED',
            'module' => 'ENTITLEMENTS',
            'target_type' => 'USER_ENTITLEMENT',
            'target_id' => $entitlement->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'PREMIUM_ENTITLEMENT_ACTIVATED',
            'module' => 'PAYMENTS',
        ]);

        /*
         * Simulate an entitlement-side write being missing after the provider
         * payment ledger has already committed. A retry with the same capture
         * must reconcile from the existing CLEARED payment without creating a
         * second payment row.
         */
        $entitlement->delete();
        $user->forceFill([
            'account_type' => 'FREE',
        ])->save();

        $retried = $service->settlePayPalCapture(
            $order->fresh(),
            $payload,
            'paypal-event-centralization-retry'
        );

        $reconciled = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $this->assertSame(
            $payment->getKey(),
            $retried->getKey()
        );
        $this->assertSame(
            $payment->getKey(),
            (int) $reconciled->payment_id
        );
        $this->assertSame(
            UserEntitlement::STATUS_ACTIVE,
            $reconciled->status
        );
        $this->assertSame(
            'PREMIUM',
            $user->fresh()->account_type
        );
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_online_gateway_existing_payment_retry_reconciles_missing_entitlement(): void
    {
        $user = $this->createApiUser();
        $order = $this->paymentOrder(
            $user,
            PaymentOrder::PROVIDER_STRIPE,
            'stripe-centralization-retry-1'
        );

        $service = app(OnlineGatewaySettlementService::class);

        $payment = $service->settle(
            $order,
            PaymentOrder::PROVIDER_STRIPE,
            'pi-centralization-retry-1',
            'USD',
            7900,
            'evt-centralization-retry-1'
        );

        $entitlement = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $this->assertSame(
            $payment->getKey(),
            (int) $entitlement->payment_id
        );

        $entitlement->delete();
        $user->forceFill([
            'account_type' => 'FREE',
        ])->save();

        $retried = $service->settle(
            $order->fresh(),
            PaymentOrder::PROVIDER_STRIPE,
            'pi-centralization-retry-1',
            'USD',
            7900,
            'evt-centralization-retry-2'
        );

        $reconciled = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $this->assertSame(
            $payment->getKey(),
            $retried->getKey()
        );
        $this->assertSame(
            $payment->getKey(),
            (int) $reconciled->payment_id
        );
        $this->assertSame(
            'PREMIUM',
            $user->fresh()->account_type
        );
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_second_cleared_online_payment_keeps_ledger_cleared_without_rebinding_existing_lifetime_entitlement(): void
    {
        $user = $this->createApiUser();

        $firstOrder = $this->paymentOrder(
            $user,
            PaymentOrder::PROVIDER_STRIPE,
            'stripe-duplicate-cleared-1'
        );

        $secondOrder = $this->paymentOrder(
            $user,
            PaymentOrder::PROVIDER_STRIPE,
            'stripe-duplicate-cleared-2'
        );

        $service = app(OnlineGatewaySettlementService::class);

        $firstPayment = $service->settle(
            $firstOrder,
            PaymentOrder::PROVIDER_STRIPE,
            'pi-duplicate-cleared-1',
            'USD',
            7900,
            'evt-duplicate-cleared-1'
        );

        $firstEntitlement = UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where(
                'product_code',
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM
            )
            ->firstOrFail();

        $firstActivation = $firstEntitlement
            ->activated_at
            ?->toISOString();

        $secondPayment = $service->settle(
            $secondOrder,
            PaymentOrder::PROVIDER_STRIPE,
            'pi-duplicate-cleared-2',
            'USD',
            7900,
            'evt-duplicate-cleared-2'
        );

        $entitlement = $firstEntitlement->fresh();

        $this->assertTrue($firstPayment->fresh()->isCleared());
        $this->assertTrue($secondPayment->fresh()->isCleared());
        $this->assertSame(
            PaymentOrder::STATUS_CLEARED,
            $firstOrder->fresh()->status
        );
        $this->assertSame(
            PaymentOrder::STATUS_CLEARED,
            $secondOrder->fresh()->status
        );

        $this->assertSame(
            $firstPayment->getKey(),
            (int) $entitlement->payment_id
        );
        $this->assertNotSame(
            $secondPayment->getKey(),
            (int) $entitlement->payment_id
        );
        $this->assertSame(
            $firstActivation,
            $entitlement->activated_at?->toISOString()
        );
        $this->assertTrue(
            $user->fresh()->canAccessPremium()
        );

        $this->assertSame(
            1,
            AuditLog::query()
                ->where(
                    'action',
                    'EDUCATION_PREMIUM_ENTITLEMENT_ACTIVATED'
                )
                ->where('module', 'ENTITLEMENTS')
                ->count()
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' =>
                'EDUCATION_PREMIUM_ENTITLEMENT_DUPLICATE_CLEARED_PAYMENT_REVIEW',
            'module' => 'ENTITLEMENTS',
            'target_type' => 'USER_ENTITLEMENT',
            'target_id' => $entitlement->getKey(),
        ]);

        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'PREMIUM_ENTITLEMENT_ACTIVATED',
            'module' => 'PAYMENTS',
        ]);

        $this->assertDatabaseCount('payments', 2);
    }

    private function paymentOrder(
        User $user,
        string $provider,
        string $key
    ): PaymentOrder {
        return PaymentOrder::query()->create([
            'user_id' => $user->getKey(),
            'product_code' =>
                PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => $provider,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' =>
                PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'client_idempotency_key_hash' =>
                hash('sha256', $key),
            'provider_reference' =>
                $provider.'-'.Str::lower($key),
            'expires_at' => now()->addDay(),
        ]);
    }
}
