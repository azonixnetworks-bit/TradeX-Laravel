<?php

namespace App\Services\Payments;

use App\Models\AffiliateQualifiedSale;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\UserEntitlement;
use App\Services\Affiliates\AffiliateCommercialService;
use App\Services\Entitlements\EducationPremiumEntitlementService;
use App\Services\Entitlements\GenericProductEntitlementService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentReversalService
{
    public function __construct(
        private readonly AffiliateCommercialService $affiliateCommercial,
        private readonly EducationPremiumEntitlementService $educationPremium,
        private readonly GenericProductEntitlementService $genericProducts
    ) {
    }

    public function recordPayPalProcessorReversal(
        Payment $payment,
        ?string $providerEventId = null
    ): PaymentAdjustment {
        return $this->recordProcessorReversal(
            payment: $payment,
            reversalCode: Payment::REVERSAL_PAYPAL_PROCESSOR,
            reason: 'PayPal reported an externally forced capture reversal.',
            providerEventId: $providerEventId,
            reversePayPalAffiliate: true
        );
    }

    public function recordStripeProcessorReversal(
        Payment $payment,
        string $reason,
        ?string $providerEventId = null
    ): PaymentAdjustment {
        if ($payment->provider !== PaymentOrder::PROVIDER_STRIPE) {
            throw new DomainException(
                'Stripe processor reversal requires a Stripe payment.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            $reason = 'Stripe reported an externally forced processor reversal.';
        }

        return $this->recordProcessorReversal(
            payment: $payment,
            reversalCode: Payment::REVERSAL_STRIPE_PROCESSOR,
            reason: $reason,
            providerEventId: $providerEventId,
            reversePayPalAffiliate: false
        );
    }

    private function recordProcessorReversal(
        Payment $payment,
        string $reversalCode,
        string $reason,
        ?string $providerEventId,
        bool $reversePayPalAffiliate
    ): PaymentAdjustment {
        $adjustment = DB::transaction(
            function () use (
                $payment,
                $reversalCode,
                $reason,
                $providerEventId
            ): PaymentAdjustment {
                $locked = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing = PaymentAdjustment::query()
                    ->where('payment_id', $locked->getKey())
                    ->where('type', PaymentAdjustment::TYPE_PAYMENT_REVERSAL)
                    ->first();

                if (
                    $locked->isPaymentReversed()
                    && $existing instanceof PaymentAdjustment
                ) {
                    $this->revokeProductEntitlement($locked);

                    return $existing;
                }

                if (! $locked->isCleared()) {
                    throw new DomainException(
                        'Only a cleared payment can be processor-reversed.'
                    );
                }

                $adjustment = PaymentAdjustment::query()->create([
                    'payment_id' => $locked->getKey(),
                    'type' => PaymentAdjustment::TYPE_PAYMENT_REVERSAL,
                    'amount_minor' => -1 * (int) $locked->gross_amount_minor,
                    'currency' => $locked->currency,
                    'reason' => $reason,
                    'provider_event_id' => $providerEventId,
                ]);

                $locked->forceFill([
                    'status' => Payment::STATUS_PAYMENT_REVERSED,
                    'payment_reversed_at' => now(),
                    'payment_reversal_code' => $reversalCode,
                    'payment_reversal_reason' => $reason,
                ])->save();

                PaymentOrder::query()
                    ->whereKey($locked->payment_order_id)
                    ->update([
                        'status' => PaymentOrder::STATUS_PAYMENT_REVERSED,
                    ]);

                $revoked = $this->revokeProductEntitlement($locked);

                $locked->loadMissing('order');
                $educationProduct =
                    $locked->order instanceof PaymentOrder
                    && $locked->order->product_code
                        === Product::CODE_EDUCATION_PREMIUM;

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYMENT_FORCED_PROCESSOR_REVERSAL_RECORDED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT',
                    targetId: $locked->getKey(),
                    oldValues: [
                        'status' => Payment::STATUS_CLEARED,
                    ],
                    newValues: [
                        'status' => Payment::STATUS_PAYMENT_REVERSED,
                        'provider' => $locked->provider,
                        'payment_reversal_code' => $reversalCode,
                        'provider_event_recorded' => $providerEventId !== null,
                        'entitlement_review_required' => false,
                        'entitlement_policy' => 'TIED_PAYMENT_ONLY',
                        'education_entitlement_revoked' =>
                            $educationProduct && $revoked !== null,
                        'product_entitlement_revoked' =>
                            $revoked !== null,
                        'copy_trade_force_close' => false,
                    ]
                );

                return $adjustment;
            },
            3
        );

        if ($reversePayPalAffiliate) {
            $this->reverseAffiliateIfPresent($payment->fresh());
        }

        return $adjustment;
    }

    private function revokeProductEntitlement(
        Payment $payment
    ): ?UserEntitlement {
        $payment->loadMissing('order');

        if (
            $payment->order instanceof PaymentOrder
            && $payment->order->product_code
                === Product::CODE_EDUCATION_PREMIUM
        ) {
            return $this->educationPremium
                ->revokeForReversedPayment($payment);
        }

        return $this->genericProducts
            ->revokeForReversedPayment($payment);
    }

    private function reverseAffiliateIfPresent(Payment $payment): void
    {
        $captureReference = trim((string) $payment->provider_capture_reference);

        if ($captureReference === '') {
            return;
        }

        $sale = AffiliateQualifiedSale::query()
            ->where(
                'source_provider',
                AffiliateQualifiedSale::PROVIDER_PAYPAL
            )
            ->where('source_reference', $captureReference)
            ->first();

        if (! $sale instanceof AffiliateQualifiedSale) {
            return;
        }

        try {
            $this->affiliateCommercial->recordForcedPaymentReversal(
                $sale,
                AffiliateQualifiedSale::REVERSAL_PAYPAL_PROCESSOR,
                'PayPal processor reversal linked to the cleared TradeX payment.'
            );
        } catch (DomainException $exception) {
            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'PAYMENT_AFFILIATE_REVERSAL_REVIEW_REQUIRED',
                module: 'PAYMENTS',
                targetType: 'PAYMENT',
                targetId: $payment->getKey(),
                newValues: [
                    'payment_uuid' => $payment->uuid,
                    'manual_review_required' => true,
                ]
            );
        }
    }
}
