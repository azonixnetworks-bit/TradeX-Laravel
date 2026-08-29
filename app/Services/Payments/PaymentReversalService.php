<?php

namespace App\Services\Payments;

use App\Models\AffiliateQualifiedSale;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Services\Affiliates\AffiliateCommercialService;
use App\Services\Entitlements\EducationPremiumEntitlementService;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentReversalService
{
    public function __construct(
        private readonly AffiliateCommercialService
            $affiliateCommercial,
        private readonly EducationPremiumEntitlementService
            $educationPremium
    ) {
    }

    public function recordPayPalProcessorReversal(
        Payment $payment,
        ?string $providerEventId = null
    ): PaymentAdjustment {
        $adjustment = DB::transaction(
            function () use (
                $payment,
                $providerEventId
            ): PaymentAdjustment {
                $locked = Payment::query()
                    ->whereKey(
                        $payment->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $existing =
                    PaymentAdjustment::query()
                        ->where(
                            'payment_id',
                            $locked->getKey()
                        )
                        ->where(
                            'type',
                            PaymentAdjustment::TYPE_PAYMENT_REVERSAL
                        )
                        ->first();

                if (
                    $locked->isPaymentReversed()
                    && $existing
                    instanceof PaymentAdjustment
                ) {
                    /*
                     * Ensure an older API-12 reversal is reconciled with
                     * the ADMIN-25 tied-payment entitlement policy.
                     */
                    $this->educationPremium
                        ->revokeForReversedPayment(
                            $locked
                        );

                    return $existing;
                }

                $reason =
                    'PayPal reported an externally forced capture reversal.';

                $adjustment =
                    PaymentAdjustment::query()
                        ->create([
                            'payment_id' =>
                                $locked->getKey(),
                            'type' =>
                                PaymentAdjustment::TYPE_PAYMENT_REVERSAL,
                            'amount_minor' =>
                                -1 * (int) $locked
                                    ->gross_amount_minor,
                            'currency' =>
                                $locked->currency,
                            'reason' =>
                                $reason,
                            'provider_event_id' =>
                                $providerEventId,
                        ]);

                $locked->forceFill([
                    'status' =>
                        Payment::STATUS_PAYMENT_REVERSED,
                    'payment_reversed_at' =>
                        now(),
                    'payment_reversal_code' =>
                        Payment::REVERSAL_PAYPAL_PROCESSOR,
                    'payment_reversal_reason' =>
                        $reason,
                ])->save();

                PaymentOrder::query()
                    ->whereKey(
                        $locked
                            ->payment_order_id
                    )
                    ->update([
                        'status' =>
                            PaymentOrder::STATUS_PAYMENT_REVERSED,
                    ]);

                $revoked =
                    $this->educationPremium
                        ->revokeForReversedPayment(
                            $locked
                        );

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYMENT_FORCED_PROCESSOR_REVERSAL_RECORDED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT',
                    targetId: $locked->getKey(),
                    oldValues: [
                        'status' =>
                            Payment::STATUS_CLEARED,
                    ],
                    newValues: [
                        'status' =>
                            Payment::STATUS_PAYMENT_REVERSED,
                        'payment_reversal_code' =>
                            Payment::REVERSAL_PAYPAL_PROCESSOR,
                        'entitlement_review_required' =>
                            false,
                        'entitlement_policy' =>
                            'ADMIN_25_TIED_PAYMENT_ONLY',
                        'education_entitlement_revoked' =>
                            $revoked !== null,
                        'copy_trade_force_close' =>
                            false,
                    ]
                );

                return $adjustment;
            },
            3
        );

        $this->reverseAffiliateIfPresent(
            $payment->fresh()
        );

        return $adjustment;
    }

    private function reverseAffiliateIfPresent(
        Payment $payment
    ): void {
        $captureReference = trim(
            (string) $payment
                ->provider_capture_reference
        );

        if ($captureReference === '') {
            return;
        }

        $sale = AffiliateQualifiedSale::query()
            ->where(
                'source_provider',
                AffiliateQualifiedSale::PROVIDER_PAYPAL
            )
            ->where(
                'source_reference',
                $captureReference
            )
            ->first();

        if (! $sale instanceof AffiliateQualifiedSale) {
            return;
        }

        try {
            $this->affiliateCommercial
                ->recordForcedPaymentReversal(
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
                    'payment_uuid' =>
                        $payment->uuid,
                    'manual_review_required' =>
                        true,
                ]
            );
        }
    }
}
