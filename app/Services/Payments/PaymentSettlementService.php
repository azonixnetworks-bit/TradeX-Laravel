<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AffiliateQualifiedSale;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Affiliates\AffiliateCommercialService;
use App\Services\Entitlements\ClearedPaymentEntitlementReconciler;
use DomainException;
use Illuminate\Support\Facades\DB;

class PaymentSettlementService
{
    public function __construct(
        private readonly PaymentMoney $money,
        private readonly AffiliateCommercialService
            $affiliateCommercial,
        private readonly ClearedPaymentEntitlementReconciler
            $entitlementReconciler
    ) {
    }

    public function settlePayPalCapture(
        PaymentOrder $order,
        array $payload,
        ?string $providerEventId = null
    ): Payment {
        $capture = $this->captureObject(
            $payload
        );

        $status = strtoupper(
            trim(
                (string) (
                    $capture['status']
                    ?? ''
                )
            )
        );

        if ($status !== 'COMPLETED') {
            throw new AuthApiException(
                409,
                'PAYPAL_CAPTURE_NOT_COMPLETED',
                'PayPal has not completed this payment.'
            );
        }

        $captureId = trim(
            (string) (
                $capture['id']
                ?? ''
            )
        );

        $currency = strtoupper(
            trim(
                (string) data_get(
                    $capture,
                    'amount.currency_code',
                    ''
                )
            )
        );

        $value = trim(
            (string) data_get(
                $capture,
                'amount.value',
                ''
            )
        );

        if (
            $captureId === ''
            || $currency === ''
            || $value === ''
        ) {
            throw new AuthApiException(
                502,
                'PAYPAL_CAPTURE_RESPONSE_INVALID',
                'PayPal returned an incomplete capture response.'
            );
        }

        $grossMinor =
            $this->money
                ->decimalToMinor(
                    $value,
                    $currency
                );

        if (
            $currency
                !== strtoupper(
                    $order->currency
                )
            || $grossMinor
                !== (int) $order
                    ->amount_minor
        ) {
            throw new AuthApiException(
                502,
                'PAYPAL_CAPTURE_AMOUNT_MISMATCH',
                'PayPal returned a payment amount that does not match the TradeX order.'
            );
        }

        $feeMinor = $this->optionalMoney(
            data_get(
                $capture,
                'seller_receivable_breakdown.paypal_fee.value'
            ),
            data_get(
                $capture,
                'seller_receivable_breakdown.paypal_fee.currency_code'
            ),
            $currency
        );

        $netMinor = $this->optionalMoney(
            data_get(
                $capture,
                'seller_receivable_breakdown.net_amount.value'
            ),
            data_get(
                $capture,
                'seller_receivable_breakdown.net_amount.currency_code'
            ),
            $currency
        );

        $captureHash = hash(
            'sha256',
            $captureId
        );

        $payment = DB::transaction(
            function () use (
                $order,
                $captureId,
                $captureHash,
                $currency,
                $grossMinor,
                $feeMinor,
                $netMinor,
                $providerEventId
            ): Payment {
                $lockedOrder =
                    PaymentOrder::query()
                        ->whereKey(
                            $order->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $existing = Payment::query()
                    ->where(
                        'provider_capture_reference_hash',
                        $captureHash
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existing instanceof Payment) {
                    if (
                        (int) $existing
                            ->payment_order_id
                        !== (int) $lockedOrder
                            ->getKey()
                    ) {
                        throw new AuthApiException(
                            409,
                            'PAYMENT_CAPTURE_REFERENCE_CONFLICT',
                            'This provider payment reference is already linked to another order.'
                        );
                    }

                    $changes = [];

                    if (
                        $existing
                            ->processor_fee_minor
                        === null
                        && $feeMinor !== null
                    ) {
                        $changes[
                            'processor_fee_minor'
                        ] = $feeMinor;
                    }

                    if (
                        $existing
                            ->net_eligible_revenue_minor
                        === null
                        && $netMinor !== null
                    ) {
                        $changes[
                            'net_eligible_revenue_minor'
                        ] = $netMinor;
                    }

                    if ($changes !== []) {
                        $existing->forceFill(
                            $changes
                        )->save();
                    }

                    if (
                        ! $lockedOrder->isCleared()
                        && ! $lockedOrder
                            ->isPaymentReversed()
                    ) {
                        $lockedOrder->forceFill([
                            'status' =>
                                PaymentOrder::STATUS_CLEARED,
                            'cleared_at' =>
                                $existing->cleared_at
                                ?? now(),
                            'failure_code' =>
                                null,
                        ])->save();
                    }

                    return $existing->refresh();
                }

                $user = User::query()
                    ->whereKey(
                        $lockedOrder->user_id
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $affiliateStatus =
                    $user->referred_by_user_id
                    !== null
                        ? Payment::AFFILIATE_PENDING
                        : Payment::AFFILIATE_NOT_APPLICABLE;

                $payment = Payment::query()
                    ->create([
                        'payment_order_id' =>
                            $lockedOrder->getKey(),
                        'user_id' =>
                            $user->getKey(),
                        'provider' =>
                            PaymentOrder::PROVIDER_PAYPAL,
                        'currency' =>
                            $currency,
                        'gross_amount_minor' =>
                            $grossMinor,
                        'processor_fee_minor' =>
                            $feeMinor,
                        'net_eligible_revenue_minor' =>
                            $netMinor,
                        'provider_capture_reference_hash' =>
                            $captureHash,
                        'provider_capture_reference' =>
                            $captureId,
                        'status' =>
                            Payment::STATUS_CLEARED,
                        'affiliate_processing_status' =>
                            $affiliateStatus,
                        'cleared_at' =>
                            now(),
                    ]);

                $lockedOrder->forceFill([
                    'status' =>
                        PaymentOrder::STATUS_CLEARED,
                    'cleared_at' =>
                        $payment->cleared_at,
                    'failure_code' =>
                        null,
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYMENT_CLEARED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT',
                    targetId: $payment->getKey(),
                    newValues: [
                        'payment_uuid' =>
                            $payment->uuid,
                        'payment_order_uuid' =>
                            $lockedOrder->uuid,
                        'provider' =>
                            PaymentOrder::PROVIDER_PAYPAL,
                        'currency' =>
                            $currency,
                        'gross_amount_minor' =>
                            $grossMinor,
                        'processor_fee_minor' =>
                            $feeMinor,
                        'net_eligible_revenue_minor' =>
                            $netMinor,
                        'provider_event_recorded' =>
                            $providerEventId
                            !== null,
                    ]
                );

                return $payment->fresh();
            },
            3
        );

        /*
         * The provider money ledger commits first. Entitlement activation is
         * reconciled only after that commit so an entitlement-side exception
         * can never roll back an externally-completed PayPal payment.
         * Existing-payment retries pass through this same reconciliation path.
         */
        if ($payment->isCleared()) {
            $this->entitlementReconciler
                ->reconcile($payment);
        }

        $this->processAffiliate(
            $payment
        );

        return $payment->fresh();
    }

    private function processAffiliate(
        Payment $payment
    ): void {
        if (
            ! in_array(
                $payment
                    ->affiliate_processing_status,
                [
                    Payment::AFFILIATE_PENDING,
                    Payment::AFFILIATE_FAILED,
                ],
                true
            )
        ) {
            return;
        }

        if (
            $payment
                ->net_eligible_revenue_minor
            === null
            || (int) $payment
                ->net_eligible_revenue_minor
                <= 0
        ) {
            return;
        }

        $buyer = User::query()
            ->find(
                $payment->user_id
            );

        if (
            ! $buyer instanceof User
            || $buyer->referred_by_user_id
                === null
        ) {
            $payment->forceFill([
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_NOT_APPLICABLE,
            ])->save();

            return;
        }

        $affiliate = User::query()
            ->find(
                $buyer->referred_by_user_id
            );

        if (! $affiliate instanceof User) {
            $payment->forceFill([
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_FAILED,
            ])->save();

            return;
        }

        try {
            $this->affiliateCommercial
                ->recordClearedPayPalSale(
                    $affiliate,
                    $buyer,
                    (string) $payment
                        ->provider_capture_reference,
                    (int) $payment
                        ->net_eligible_revenue_minor,
                    $payment->cleared_at
                );

            $payment->forceFill([
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_PROCESSED,
                'affiliate_processed_at' =>
                    now(),
            ])->save();
        } catch (DomainException $exception) {
            $payment->forceFill([
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_FAILED,
            ])->save();

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'PAYMENT_AFFILIATE_PROCESSING_DEFERRED',
                module: 'PAYMENTS',
                targetType: 'PAYMENT',
                targetId: $payment->getKey(),
                newValues: [
                    'payment_uuid' =>
                        $payment->uuid,
                    'retry_required' =>
                        true,
                ]
            );
        }
    }

    private function captureObject(
        array $payload
    ): array {
        $nested = data_get(
            $payload,
            'purchase_units.0.payments.captures.0'
        );

        if (is_array($nested)) {
            return $nested;
        }

        if (
            isset(
                $payload['id'],
                $payload['status'],
                $payload['amount']
            )
        ) {
            return $payload;
        }

        throw new AuthApiException(
            502,
            'PAYPAL_CAPTURE_RESPONSE_INVALID',
            'PayPal returned an invalid capture response.'
        );
    }

    private function optionalMoney(
        mixed $value,
        mixed $currency,
        string $expectedCurrency
    ): ?int {
        if (
            ! is_string($value)
            && ! is_numeric($value)
        ) {
            return null;
        }

        $currency = strtoupper(
            trim(
                (string) $currency
            )
        );

        if (
            $currency !== ''
            && $currency
                !== $expectedCurrency
        ) {
            return null;
        }

        return $this->money
            ->decimalToMinor(
                (string) $value,
                $expectedCurrency
            );
    }
}
