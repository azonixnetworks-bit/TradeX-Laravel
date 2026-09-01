<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PayPalWebhookService
{
    private const EVENT_CAPTURE_COMPLETED =
        'PAYMENT.CAPTURE.COMPLETED';

    private const EVENT_CAPTURE_PENDING =
        'PAYMENT.CAPTURE.PENDING';

    private const EVENT_CAPTURE_DENIED =
        'PAYMENT.CAPTURE.DENIED';

    private const EVENT_CAPTURE_DECLINED =
        'PAYMENT.CAPTURE.DECLINED';

    private const EVENT_CAPTURE_REFUNDED =
        'PAYMENT.CAPTURE.REFUNDED';

    private const EVENT_CAPTURE_REVERSED =
        'PAYMENT.CAPTURE.REVERSED';

    private const EVENT_APPROVAL_REVERSED =
        'CHECKOUT.PAYMENT-APPROVAL.REVERSED';

    private const EVENT_DISPUTE_CREATED =
        'CUSTOMER.DISPUTE.CREATED';

    private const EVENT_DISPUTE_UPDATED =
        'CUSTOMER.DISPUTE.UPDATED';

    private const EVENT_DISPUTE_RESOLVED =
        'CUSTOMER.DISPUTE.RESOLVED';

    public function __construct(
        private readonly PayPalGatewayService
            $paypal,
        private readonly PaymentSettlementService
            $settlement,
        private readonly PaymentReversalService
            $reversals
    ) {
    }

    public function handle(
        Request $request
    ): array {
        if (! $this->paypal->verifyWebhook(
            $request
        )) {
            throw new AuthApiException(
                401,
                'PAYPAL_WEBHOOK_SIGNATURE_INVALID',
                'The PayPal webhook signature could not be verified.'
            );
        }

        $payload = $request->json()->all();

        $eventId = trim(
            (string) (
                $payload['id']
                ?? ''
            )
        );

        $eventType = strtoupper(
            trim(
                (string) (
                    $payload['event_type']
                    ?? ''
                )
            )
        );

        if (
            $eventId === ''
            || $eventType === ''
        ) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_EVENT_INVALID',
                'The PayPal webhook event is missing required event metadata.'
            );
        }

        $payloadHash = hash(
            'sha256',
            (string) $request->getContent()
        );

        $event =
            PaymentWebhookEvent::query()
                ->firstOrCreate(
                    [
                        'provider' =>
                            PaymentWebhookEvent::PROVIDER_PAYPAL,

                        'event_id' =>
                            $eventId,
                    ],
                    [
                        'event_type' =>
                            $eventType,

                        'payload_sha256' =>
                            $payloadHash,

                        'status' =>
                            PaymentWebhookEvent::STATUS_RECEIVED,
                    ]
                );

        if (
            ! hash_equals(
                (string) $event->payload_sha256,
                $payloadHash
            )
            || $event->event_type
                !== $eventType
        ) {
            throw new AuthApiException(
                409,
                'PAYPAL_WEBHOOK_EVENT_CONFLICT',
                'The PayPal event identifier was previously received with different event data.'
            );
        }

        if (
            in_array(
                $event->status,
                [
                    PaymentWebhookEvent::STATUS_PROCESSED,
                    PaymentWebhookEvent::STATUS_IGNORED,
                ],
                true
            )
        ) {
            return $this->present(
                $event,
                true
            );
        }

        try {
            $handled = match (
                $eventType
            ) {
                self::EVENT_CAPTURE_COMPLETED =>
                    $this->handleCompleted(
                        $payload,
                        $eventId
                    ),

                self::EVENT_CAPTURE_PENDING =>
                    $this->handlePending(
                        $payload,
                        $eventId
                    ),

                self::EVENT_CAPTURE_DENIED,
                self::EVENT_CAPTURE_DECLINED =>
                    $this->handleDenied(
                        $payload,
                        $eventId
                    ),

                self::EVENT_CAPTURE_REFUNDED =>
                    $this->handleRefunded(
                        $payload,
                        $eventId
                    ),

                self::EVENT_CAPTURE_REVERSED =>
                    $this->handleReversed(
                        $payload,
                        $eventId
                    ),

                self::EVENT_APPROVAL_REVERSED =>
                    $this->handleApprovalReversed(
                        $payload,
                        $eventId
                    ),

                self::EVENT_DISPUTE_CREATED,
                self::EVENT_DISPUTE_UPDATED,
                self::EVENT_DISPUTE_RESOLVED =>
                    $this->handleDispute(
                        $payload,
                        $eventType,
                        $eventId
                    ),

                default =>
                    false,
            };

            $event->forceFill([
                'status' =>
                    $handled
                        ? PaymentWebhookEvent::STATUS_PROCESSED
                        : PaymentWebhookEvent::STATUS_IGNORED,

                'processed_at' =>
                    now(),
            ])->save();

            return $this->present(
                $event->fresh(),
                false
            );
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' =>
                    PaymentWebhookEvent::STATUS_FAILED,

                'processed_at' =>
                    null,
            ])->save();

            throw $exception;
        }
    }

    private function handleCompleted(
        array $payload,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal webhook resource is invalid.'
            );
        }

        $order =
            $this->resolveOrderFromCaptureResource(
                $resource
            );

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        $this->settlement
            ->settlePayPalCapture(
                $order,
                $resource,
                $eventId
            );

        return true;
    }

    private function handlePending(
        array $payload,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal pending capture resource is invalid.'
            );
        }

        $order =
            $this->resolveOrderFromCaptureResource(
                $resource
            );

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        DB::transaction(
            function () use (
                $order,
                $eventId
            ): void {
                $locked =
                    PaymentOrder::query()
                        ->whereKey(
                            $order->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->isCleared()
                    || $locked->isPaymentReversed()
                ) {
                    return;
                }

                if (
                    in_array(
                        $locked->status,
                        [
                            PaymentOrder::STATUS_CREATED,
                            PaymentOrder::STATUS_AWAITING_USER_ACTION,
                            PaymentOrder::STATUS_FAILED,
                        ],
                        true
                    )
                ) {
                    $locked->forceFill([
                        'status' =>
                            PaymentOrder::STATUS_PROCESSING,
                        'failure_code' =>
                            null,
                    ])->save();
                }

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYPAL_CAPTURE_PENDING',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT_ORDER',
                    targetId: $locked->getKey(),
                    newValues: [
                        'payment_order_uuid' =>
                            $locked->uuid,
                        'provider_event_recorded' =>
                            $eventId !== '',
                        'premium_activation' =>
                            false,
                    ]
                );
            },
            3
        );

        return true;
    }

    private function handleDenied(
        array $payload,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal denied capture resource is invalid.'
            );
        }

        $order =
            $this->resolveOrderFromCaptureResource(
                $resource
            );

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        DB::transaction(
            function () use (
                $order,
                $eventId
            ): void {
                $locked =
                    PaymentOrder::query()
                        ->whereKey(
                            $order->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->isCleared()
                    || $locked->isPaymentReversed()
                    || Payment::query()
                        ->where(
                            'payment_order_id',
                            $locked->getKey()
                        )
                        ->exists()
                ) {
                    return;
                }

                $locked->forceFill([
                    'status' =>
                        PaymentOrder::STATUS_FAILED,
                    'failure_code' =>
                        'PAYPAL_CAPTURE_DENIED',
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYPAL_CAPTURE_DENIED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT_ORDER',
                    targetId: $locked->getKey(),
                    newValues: [
                        'payment_order_uuid' =>
                            $locked->uuid,
                        'provider_event_recorded' =>
                            $eventId !== '',
                        'premium_activation' =>
                            false,
                    ]
                );
            },
            3
        );

        return true;
    }

    private function handleApprovalReversed(
        array $payload,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal approval reversal resource is invalid.'
            );
        }

        $order =
            $this->resolveOrderFromCheckoutResource(
                $resource
            );

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        DB::transaction(
            function () use (
                $order,
                $eventId
            ): void {
                $locked =
                    PaymentOrder::query()
                        ->whereKey(
                            $order->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->isCleared()
                    || $locked->isPaymentReversed()
                    || Payment::query()
                        ->where(
                            'payment_order_id',
                            $locked->getKey()
                        )
                        ->exists()
                ) {
                    return;
                }

                $locked->forceFill([
                    'status' =>
                        PaymentOrder::STATUS_CANCELLED,
                    'failure_code' =>
                        'PAYPAL_PAYMENT_APPROVAL_REVERSED',
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PAYPAL_PAYMENT_APPROVAL_REVERSED',
                    module: 'PAYMENTS',
                    targetType: 'PAYMENT_ORDER',
                    targetId: $locked->getKey(),
                    newValues: [
                        'payment_order_uuid' =>
                            $locked->uuid,
                        'provider_event_recorded' =>
                            $eventId !== '',
                    ]
                );
            },
            3
        );

        return true;
    }

    private function handleRefunded(
        array $payload,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal refunded capture resource is invalid.'
            );
        }

        $captureId = trim(
            (string) (
                $resource['id']
                ?? ''
            )
        );

        if ($captureId === '') {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal capture reference is missing.'
            );
        }

        $payment =
            $this->paymentForCapture(
                $captureId
            );

        if (! $payment instanceof Payment) {
            return false;
        }

        $status = strtoupper(
            trim(
                (string) (
                    $resource['status']
                    ?? ''
                )
            )
        );

        if ($status === 'PARTIALLY_REFUNDED') {
            $this->recordPaymentReview(
                $payment,
                'PAYPAL_PARTIAL_REFUND_REVIEW_REQUIRED',
                $eventId,
                [
                    'capture_status' =>
                        $status,
                ]
            );

            return true;
        }

        if ($status !== 'REFUNDED') {
            $this->recordPaymentReview(
                $payment,
                'PAYPAL_REFUND_STATUS_REVIEW_REQUIRED',
                $eventId,
                [
                    'capture_status' =>
                        $status,
                ]
            );

            return true;
        }

        $currency = strtoupper(
            trim(
                (string) data_get(
                    $resource,
                    'amount.currency_code',
                    ''
                )
            )
        );

        $value = trim(
            (string) data_get(
                $resource,
                'amount.value',
                ''
            )
        );

        if (
            $currency !== ''
            && $currency
                !== strtoupper(
                    (string) $payment->currency
                )
        ) {
            throw new AuthApiException(
                409,
                'PAYPAL_REFUND_AMOUNT_MISMATCH',
                'The PayPal refund does not match the cleared TradeX payment.'
            );
        }

        if (
            $value !== ''
            && $value
                !== number_format(
                    ((int) $payment
                        ->gross_amount_minor) / 100,
                    2,
                    '.',
                    ''
                )
        ) {
            throw new AuthApiException(
                409,
                'PAYPAL_REFUND_AMOUNT_MISMATCH',
                'The PayPal refund does not match the cleared TradeX payment.'
            );
        }

        /*
         * TradeX exposes no voluntary refund endpoint. If PayPal reports a
         * completed full external refund, financial truth still has to be
         * reconciled and only the entitlement tied to this payment is revoked.
         */
        $this->reversals
            ->recordPayPalProcessorReversal(
                $payment,
                $eventId
            );

        return true;
    }

    private function handleReversed(
        array $payload,
        string $eventId
    ): bool {
        $captureId = trim(
            (string) data_get(
                $payload,
                'resource.id',
                ''
            )
        );

        if ($captureId === '') {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal capture reference is missing.'
            );
        }

        $payment =
            $this->paymentForCapture(
                $captureId
            );

        if (! $payment instanceof Payment) {
            return false;
        }

        $this->reversals
            ->recordPayPalProcessorReversal(
                $payment,
                $eventId
            );

        return true;
    }

    private function handleDispute(
        array $payload,
        string $eventType,
        string $eventId
    ): bool {
        $resource = data_get(
            $payload,
            'resource'
        );

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'PAYPAL_WEBHOOK_RESOURCE_INVALID',
                'The PayPal dispute resource is invalid.'
            );
        }

        $payment =
            $this->paymentFromDispute(
                $resource
            );

        if (! $payment instanceof Payment) {
            return false;
        }

        $this->recordPaymentReview(
            $payment,
            match ($eventType) {
                self::EVENT_DISPUTE_CREATED =>
                    'PAYPAL_DISPUTE_CREATED_REVIEW_REQUIRED',

                self::EVENT_DISPUTE_UPDATED =>
                    'PAYPAL_DISPUTE_UPDATED_REVIEW_REQUIRED',

                self::EVENT_DISPUTE_RESOLVED =>
                    'PAYPAL_DISPUTE_RESOLVED_REVIEW_REQUIRED',

                default =>
                    'PAYPAL_DISPUTE_REVIEW_REQUIRED',
            },
            $eventId,
            [
                'dispute_status' =>
                    trim(
                        (string) (
                            $resource['status']
                            ?? ''
                        )
                    ),

                'dispute_reason' =>
                    trim(
                        (string) (
                            $resource['reason']
                            ?? ''
                        )
                    ),

                'dispute_outcome' =>
                    trim(
                        (string) data_get(
                            $resource,
                            'dispute_outcome.outcome_code',
                            ''
                        )
                    ),

                /*
                 * An open/resolved dispute alone never revokes Premium.
                 * A verified capture REFUNDED/REVERSED financial event is the
                 * authoritative trigger for the tied-payment reversal.
                 */
                'automatic_entitlement_change' =>
                    false,
            ]
        );

        return true;
    }

    private function resolveOrderFromCaptureResource(
        array $resource
    ): ?PaymentOrder {
        $tradeXUuid = trim(
            (string) (
                $resource['custom_id']
                ?? ''
            )
        );

        if ($tradeXUuid !== '') {
            $order =
                PaymentOrder::query()
                    ->where(
                        'uuid',
                        $tradeXUuid
                    )
                    ->where(
                        'provider',
                        PaymentOrder::PROVIDER_PAYPAL
                    )
                    ->first();

            if ($order instanceof PaymentOrder) {
                return $order;
            }
        }

        $providerOrderId = trim(
            (string) data_get(
                $resource,
                'supplementary_data.related_ids.order_id',
                ''
            )
        );

        if ($providerOrderId === '') {
            return null;
        }

        return PaymentOrder::query()
            ->where(
                'provider',
                PaymentOrder::PROVIDER_PAYPAL
            )
            ->where(
                'provider_reference',
                $providerOrderId
            )
            ->first();
    }

    private function resolveOrderFromCheckoutResource(
        array $resource
    ): ?PaymentOrder {
        $providerOrderId = trim(
            (string) (
                $resource['id']
                ?? $resource['order_id']
                ?? ''
            )
        );

        if ($providerOrderId === '') {
            return null;
        }

        return PaymentOrder::query()
            ->where(
                'provider',
                PaymentOrder::PROVIDER_PAYPAL
            )
            ->where(
                'provider_reference',
                $providerOrderId
            )
            ->first();
    }

    private function paymentForCapture(
        string $captureId
    ): ?Payment {
        $captureId = trim(
            $captureId
        );

        if ($captureId === '') {
            return null;
        }

        return Payment::query()
            ->where(
                'provider',
                PaymentOrder::PROVIDER_PAYPAL
            )
            ->where(
                'provider_capture_reference_hash',
                hash(
                    'sha256',
                    $captureId
                )
            )
            ->first();
    }

    private function paymentFromDispute(
        array $resource
    ): ?Payment {
        $transactions = data_get(
            $resource,
            'disputed_transactions',
            []
        );

        if (! is_array($transactions)) {
            return null;
        }

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            foreach (
                [
                    $transaction[
                        'seller_transaction_id'
                    ] ?? null,
                    $transaction[
                        'buyer_transaction_id'
                    ] ?? null,
                ]
                as $reference
            ) {
                $payment =
                    $this->paymentForCapture(
                        trim(
                            (string) $reference
                        )
                    );

                if ($payment instanceof Payment) {
                    return $payment;
                }
            }
        }

        return null;
    }

    private function recordPaymentReview(
        Payment $payment,
        string $action,
        string $providerEventId,
        array $context = []
    ): void {
        AuditLog::record(
            actorType: 'SYSTEM',
            actorId: null,
            action: $action,
            module: 'PAYMENTS',
            targetType: 'PAYMENT',
            targetId: $payment->getKey(),
            newValues: [
                'payment_uuid' =>
                    $payment->uuid,

                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,

                'provider_event_recorded' =>
                    $providerEventId !== '',

                'manual_review_required' =>
                    true,

                ...$context,
            ]
        );
    }

    private function present(
        PaymentWebhookEvent $event,
        bool $duplicate
    ): array {
        return [
            'webhook_event_uuid' =>
                $event->uuid,

            'event_type' =>
                $event->event_type,

            'status' =>
                $event->status,

            'duplicate' =>
                $duplicate,

            'processed_at' =>
                $event->processed_at
                    ?->toISOString(),
        ];
    }
}