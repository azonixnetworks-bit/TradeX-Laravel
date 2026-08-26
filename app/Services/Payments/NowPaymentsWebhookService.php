<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use Illuminate\Http\Request;
use Throwable;

class NowPaymentsWebhookService
{
    public function __construct(
        private readonly PaymentSettlementService $settlement,
        private readonly PaymentMoney $money
    ) {
    }

    public function handle(Request $request): array
    {
        $raw = (string) $request->getContent();
        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            throw new AuthApiException(422, 'NOWPAYMENTS_IPN_INVALID', 'NOWPayments IPN JSON is invalid.');
        }

        $this->verifySignature($payload, (string) $request->header('x-nowpayments-sig', ''));

        $paymentId = trim((string) ($payload['payment_id'] ?? ''));
        $status = strtolower(trim((string) ($payload['payment_status'] ?? '')));
        $orderUuid = trim((string) ($payload['order_id'] ?? ''));

        if ($paymentId === '' || $status === '' || $orderUuid === '') {
            throw new AuthApiException(422, 'NOWPAYMENTS_IPN_INVALID', 'NOWPayments IPN is missing required payment metadata.');
        }

        $payloadHash = hash('sha256', $raw);
        $eventId = $paymentId.':'.$status.':'.substr($payloadHash, 0, 24);
        $eventType = 'PAYMENT.'.strtoupper($status);

        $event = PaymentWebhookEvent::query()->firstOrCreate([
            'provider' => PaymentWebhookEvent::PROVIDER_NOWPAYMENTS,
            'event_id' => $eventId,
        ], [
            'event_type' => $eventType,
            'payload_sha256' => $payloadHash,
            'status' => PaymentWebhookEvent::STATUS_RECEIVED,
        ]);

        if (! hash_equals((string) $event->payload_sha256, $payloadHash)) {
            throw new AuthApiException(409, 'NOWPAYMENTS_IPN_EVENT_CONFLICT', 'NOWPayments IPN event data conflicts with a previous callback.');
        }

        if (in_array($event->status, [PaymentWebhookEvent::STATUS_PROCESSED, PaymentWebhookEvent::STATUS_IGNORED], true)) {
            return $this->present($event, true);
        }

        try {
            $handled = $this->processStatus($payload, $paymentId, $status, $orderUuid, $eventId);
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

    private function processStatus(array $payload, string $paymentId, string $status, string $orderUuid, string $eventId): bool
    {
        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->where('provider', PaymentOrder::PROVIDER_NOWPAYMENTS)
            ->first();

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        if ($status === 'finished') {
            $currency = strtoupper(trim((string) ($payload['price_currency'] ?? '')));
            $priceAmount = trim((string) ($payload['price_amount'] ?? ''));
            if ($currency === '' || $priceAmount === '') {
                throw new AuthApiException(422, 'NOWPAYMENTS_IPN_AMOUNT_INVALID', 'NOWPayments finished IPN is missing price amount data.');
            }

            $grossMinor = $this->money->decimalToMinor($priceAmount, $currency);
            $this->settlement->settleOnlineProviderPayment(
                order: $order,
                provider: PaymentOrder::PROVIDER_NOWPAYMENTS,
                providerPaymentReference: $paymentId,
                currency: $currency,
                grossMinor: $grossMinor,
                providerEventId: $eventId
            );

            return true;
        }

        if (in_array($status, ['failed', 'expired', 'cancelled'], true) && ! $order->isCleared()) {
            $order->forceFill([
                'status' => PaymentOrder::STATUS_FAILED,
                'failure_code' => 'NOWPAYMENTS_'.strtoupper($status),
            ])->save();
            return true;
        }

        if (in_array($status, ['waiting', 'confirming', 'confirmed', 'sending', 'partially_paid', 'wrong_asset_confirmed'], true)) {
            if (! $order->isCleared()) {
                $order->forceFill([
                    'status' => PaymentOrder::STATUS_PROCESSING,
                    'failure_code' => $status === 'partially_paid' ? 'NOWPAYMENTS_PARTIALLY_PAID_REVIEW' : null,
                ])->save();
            }
            return true;
        }

        return false;
    }

    private function verifySignature(array $payload, string $received): void
    {
        $secret = trim((string) config('tradex_integrations.nowpayments.ipn_secret'));
        $received = trim($received);
        if ($secret === '' || $received === '') {
            throw new AuthApiException(401, 'NOWPAYMENTS_IPN_SIGNATURE_INVALID', 'NOWPayments IPN signature is missing or cannot be verified.');
        }

        $sorted = $this->sortRecursive($payload);
        $encoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($encoded)) {
            throw new AuthApiException(422, 'NOWPAYMENTS_IPN_INVALID', 'NOWPayments IPN could not be canonicalized.');
        }

        $expected = hash_hmac('sha512', $encoded, $secret);
        if (! hash_equals(strtolower($expected), strtolower($received))) {
            throw new AuthApiException(401, 'NOWPAYMENTS_IPN_SIGNATURE_INVALID', 'NOWPayments IPN signature could not be verified.');
        }
    }

    private function sortRecursive(array $value): array
    {
        ksort($value);
        foreach ($value as $key => $child) {
            if (is_array($child)) {
                $value[$key] = $this->sortRecursive($child);
            }
        }
        return $value;
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
