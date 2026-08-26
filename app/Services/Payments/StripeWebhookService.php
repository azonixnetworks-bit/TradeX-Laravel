<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Throwable;

class StripeWebhookService
{
    private const EVENT_CHECKOUT_COMPLETED = 'checkout.session.completed';
    private const EVENT_ASYNC_SUCCEEDED = 'checkout.session.async_payment_succeeded';

    public function __construct(
        private readonly PaymentSettlementService $settlement
    ) {
    }

    public function handle(Request $request): array
    {
        $raw = (string) $request->getContent();
        $this->verifySignature($raw, (string) $request->header('Stripe-Signature', ''));

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new AuthApiException(422, 'STRIPE_WEBHOOK_EVENT_INVALID', 'Stripe webhook JSON is invalid.');
        }

        $eventId = trim((string) ($payload['id'] ?? ''));
        $eventType = trim((string) ($payload['type'] ?? ''));
        if ($eventId === '' || $eventType === '') {
            throw new AuthApiException(422, 'STRIPE_WEBHOOK_EVENT_INVALID', 'Stripe webhook event metadata is missing.');
        }

        $payloadHash = hash('sha256', $raw);
        $event = PaymentWebhookEvent::query()->firstOrCreate([
            'provider' => PaymentWebhookEvent::PROVIDER_STRIPE,
            'event_id' => $eventId,
        ], [
            'event_type' => $eventType,
            'payload_sha256' => $payloadHash,
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
        ]);

        if (! hash_equals((string) $event->payload_sha256, $payloadHash) || $event->event_type !== $eventType) {
            throw new AuthApiException(409, 'STRIPE_WEBHOOK_EVENT_CONFLICT', 'The Stripe event identifier was previously received with different data.');
        }

        if (in_array($event->status, [PaymentWebhookEvent::STATUS_PROCESSED, PaymentWebhookEvent::STATUS_IGNORED], true)) {
            return $this->present($event, true);
        }

        try {
            $handled = in_array($eventType, [self::EVENT_CHECKOUT_COMPLETED, self::EVENT_ASYNC_SUCCEEDED], true)
                ? $this->handleCheckoutCompleted($payload, $eventId)
                : false;

            $event->forceFill([
                'status' => $handled ? PaymentWebhookEvent::STATUS_PROCESSED : PaymentWebhookEvent::STATUS_IGNORED,
                'processed_at' => now(),
            ])->save();

            return $this->present($event->fresh(), false);
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => PaymentWebhookEvent::STATUS_FAILED,
                'processed_at' => null,
            ])->save();
            throw $exception;
        }
    }

    private function handleCheckoutCompleted(array $payload, string $eventId): bool
    {
        $session = data_get($payload, 'data.object');
        if (! is_array($session)) {
            throw new AuthApiException(422, 'STRIPE_WEBHOOK_RESOURCE_INVALID', 'Stripe checkout resource is invalid.');
        }

        if (strtolower(trim((string) ($session['payment_status'] ?? ''))) !== 'paid') {
            return false;
        }

        $orderUuid = trim((string) ($session['client_reference_id'] ?? data_get($session, 'metadata.tradex_order_uuid', '')));
        $sessionId = trim((string) ($session['id'] ?? ''));
        $paymentIntentId = trim((string) ($session['payment_intent'] ?? ''));

        if ($orderUuid === '' || $sessionId === '' || $paymentIntentId === '') {
            throw new AuthApiException(422, 'STRIPE_WEBHOOK_RESOURCE_INVALID', 'Stripe checkout settlement references are incomplete.');
        }

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->where('provider', PaymentOrder::PROVIDER_STRIPE)
            ->first();

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        if ($order->provider_reference !== null && ! hash_equals((string) $order->provider_reference, $sessionId)) {
            throw new AuthApiException(409, 'STRIPE_CHECKOUT_REFERENCE_CONFLICT', 'Stripe checkout session does not match the TradeX order.');
        }

        $currency = strtoupper(trim((string) ($session['currency'] ?? '')));
        $grossMinor = (int) ($session['amount_total'] ?? 0);

        $this->settlement->settleOnlineProviderPayment(
            order: $order,
            provider: PaymentOrder::PROVIDER_STRIPE,
            providerPaymentReference: $paymentIntentId,
            currency: $currency,
            grossMinor: $grossMinor,
            providerEventId: $eventId
        );

        return true;
    }

    private function verifySignature(string $payload, string $header): void
    {
        $secret = trim((string) config('tradex_integrations.stripe.webhook_secret'));
        if ($secret === '' || $header === '') {
            throw new AuthApiException(401, 'STRIPE_WEBHOOK_SIGNATURE_INVALID', 'Stripe webhook signature is missing or cannot be verified.');
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        $tolerance = max(60, (int) config('tradex_integrations.stripe.webhook_tolerance_seconds', 300));
        if ($timestamp === null || abs(time() - $timestamp) > $tolerance || $signatures === []) {
            throw new AuthApiException(401, 'STRIPE_WEBHOOK_SIGNATURE_INVALID', 'Stripe webhook signature is outside the accepted verification window.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new AuthApiException(401, 'STRIPE_WEBHOOK_SIGNATURE_INVALID', 'Stripe webhook signature could not be verified.');
    }

    private function present(PaymentWebhookEvent $event, bool $duplicate): array
    {
        return [
            'webhook_event_uuid' => $event->uuid,
            'event_type' => $event->event_type,
            'status' => $event->status,
            'duplicate' => $duplicate,
            'processed_at' => $event->processed_at?->toISOString(),
        ];
    }
}
