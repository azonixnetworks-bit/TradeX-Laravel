<?php

namespace Tests\Feature\Admin25;

use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\UserEntitlement;
use App\Services\Entitlements\EducationPremiumEntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class EducationPremiumReversalTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_exact_reversed_payment_revokes_its_active_education_entitlement_only(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'education_premium_entitlement' => false,
            'premium_activated_at' => now(),
        ]);

        [$order, $payment] =
            $this->payment(
                $user->getKey(),
                'REV-1',
                Payment::STATUS_PAYMENT_REVERSED
            );

        UserEntitlement::query()
            ->create([
                'user_id' =>
                    $user->getKey(),
                'product_code' =>
                    Product::CODE_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
                'activated_at' =>
                    now(),
                'payment_id' =>
                    $payment->getKey(),
            ]);

        $service = app(
            EducationPremiumEntitlementService::class
        );

        $revoked =
            $service
                ->revokeForReversedPayment(
                    $payment
                );

        $this->assertNotNull($revoked);
        $this->assertSame(
            UserEntitlement::STATUS_REVOKED,
            $revoked->status
        );

        $user->refresh();

        $this->assertSame(
            'FREE',
            $user->account_type
        );
        $this->assertFalse(
            $user->canAccessPremium()
        );
    }

    public function test_reversal_of_older_payment_cannot_revoke_entitlement_rebound_to_newer_cleared_payment(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'education_premium_entitlement' => false,
            'premium_activated_at' => now(),
        ]);

        [, $oldPayment] =
            $this->payment(
                $user->getKey(),
                'REV-OLD',
                Payment::STATUS_PAYMENT_REVERSED
            );

        [, $newPayment] =
            $this->payment(
                $user->getKey(),
                'REV-NEW',
                Payment::STATUS_CLEARED
            );

        $entitlement =
            UserEntitlement::query()
                ->create([
                    'user_id' =>
                        $user->getKey(),
                    'product_code' =>
                        Product::CODE_EDUCATION_PREMIUM,
                    'status' =>
                        UserEntitlement::STATUS_ACTIVE,
                    'activated_at' =>
                        now(),
                    'payment_id' =>
                        $newPayment->getKey(),
                ]);

        $service = app(
            EducationPremiumEntitlementService::class
        );

        $this->assertNull(
            $service
                ->revokeForReversedPayment(
                    $oldPayment
                )
        );

        $entitlement->refresh();

        $this->assertSame(
            UserEntitlement::STATUS_ACTIVE,
            $entitlement->status
        );
        $this->assertSame(
            $newPayment->getKey(),
            $entitlement->payment_id
        );
        $this->assertTrue(
            $user->fresh()
                ->canAccessPremium()
        );
    }

    private function payment(
        int $userId,
        string $reference,
        string $paymentStatus
    ): array {
        $order = PaymentOrder::query()
            ->create([
                'user_id' =>
                    $userId,
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
                    $paymentStatus
                        === Payment::STATUS_PAYMENT_REVERSED
                            ? PaymentOrder::STATUS_PAYMENT_REVERSED
                            : PaymentOrder::STATUS_CLEARED,
                'client_idempotency_key_hash' =>
                    hash(
                        'sha256',
                        (string) Str::uuid()
                    ),
                'provider_reference' =>
                    'ORDER-'.$reference,
                'cleared_at' =>
                    now(),
            ]);

        $payment = Payment::query()
            ->create([
                'payment_order_id' =>
                    $order->getKey(),
                'user_id' =>
                    $userId,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'currency' =>
                    'USD',
                'gross_amount_minor' =>
                    7900,
                'processor_fee_minor' =>
                    300,
                'net_eligible_revenue_minor' =>
                    7600,
                'provider_capture_reference_hash' =>
                    hash(
                        'sha256',
                        'CAPTURE-'.$reference
                    ),
                'provider_capture_reference' =>
                    'CAPTURE-'.$reference,
                'status' =>
                    $paymentStatus,
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_NOT_APPLICABLE,
                'cleared_at' =>
                    now(),
                'payment_reversed_at' =>
                    $paymentStatus
                        === Payment::STATUS_PAYMENT_REVERSED
                            ? now()
                            : null,
                'payment_reversal_code' =>
                    $paymentStatus
                        === Payment::STATUS_PAYMENT_REVERSED
                            ? Payment::REVERSAL_PAYPAL_PROCESSOR
                            : null,
                'payment_reversal_reason' =>
                    $paymentStatus
                        === Payment::STATUS_PAYMENT_REVERSED
                            ? 'Test reversal'
                            : null,
            ]);

        return [
            $order,
            $payment,
        ];
    }
}
