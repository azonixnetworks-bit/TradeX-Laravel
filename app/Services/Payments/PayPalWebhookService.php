<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Throwable;

class PayPalWebhookService
{
    private const EVENT_CAPTURE_COMPLETED =
        'PAYMENT.CAPTURE.COMPLETED';

    private const EVENT_CAPTURE_REVERSED =
        'PAYMENT.CAPTURE.REVERSED';

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

                self::EVENT_CAPTURE_REVERSED =>
                    $this->handleReversed(
                        $payload,
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
            /*
            | A verified PayPal event that cannot be tied to a TradeX order is
            | ignored rather than used to create any entitlement.
            */
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
            Payment::query()
                ->where(
                    'provider_capture_reference_hash',
                    hash(
                        'sha256',
                        $captureId
                    )
                )
                ->first();

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
