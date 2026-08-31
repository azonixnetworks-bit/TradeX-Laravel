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

class StripeWebhookService
{
    private const EVENT_CHECKOUT_COMPLETED = 'checkout.session.completed';
    private const EVENT_ASYNC_SUCCEEDED = 'checkout.session.async_payment_succeeded';
    private const EVENT_CHECKOUT_EXPIRED = 'checkout.session.expired';
    private const EVENT_CHARGE_REFUNDED = 'charge.refunded';
    private const EVENT_DISPUTE_CREATED = 'charge.dispute.created';
    private const EVENT_DISPUTE_CLOSED = 'charge.dispute.closed';

    public function __construct(
        private readonly OnlineGatewaySettlementService $settlement,
        private readonly PaymentReversalService $reversals
    ) {
    }

    public function handle(Request $request): array
    {
        $raw = (string) $request->getContent();
        $this->verifySignature(
            $raw,
            (string) $request->header('Stripe-Signature', '')
        );

        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw new AuthApiException(
                422,
                'STRIPE_WEBHOOK_EVENT_INVALID',
                'Stripe webhook JSON is invalid.'
            );
        }

        $eventId = trim((string) ($payload['id'] ?? ''));
        $eventType = trim((string) ($payload['type'] ?? ''));

        if ($eventId === '' || $eventType === '') {
            throw new AuthApiException(
                422,
                'STRIPE_WEBHOOK_EVENT_INVALID',
                'Stripe webhook event metadata is missing.'
            );
        }

        $payloadHash = hash('sha256', $raw);
        $event = PaymentWebhookEvent::query()->firstOrCreate(
            [
                'provider' => PaymentWebhookEvent::PROVIDER_STRIPE,
                'event_id' => $eventId,
            ],
            [
                'event_type' => $eventType,
                'payload_sha256' => $payloadHash,
                'status' => PaymentWebhookEvent::STATUS_RECEIVED,
            ]
        );

        if (
            ! hash_equals((string) $event->payload_sha256, $payloadHash)
            || $event->event_type !== $eventType
        ) {
            throw new AuthApiException(
                409,
                'STRIPE_WEBHOOK_EVENT_CONFLICT',
                'The Stripe event identifier was previously received with different data.'
            );
        }

        if (in_array(
            $event->status,
            [
                PaymentWebhookEvent::STATUS_PROCESSED,
                PaymentWebhookEvent::STATUS_IGNORED,
            ],
            true
        )) {
            return $this->present($event, true);
        }

        try {
            $handled = match ($eventType) {
                self::EVENT_CHECKOUT_COMPLETED,
                self::EVENT_ASYNC_SUCCEEDED =>
                    $this->handleCheckoutCompleted($payload, $eventId),
                self::EVENT_CHECKOUT_EXPIRED =>
                    $this->handleCheckoutExpired($payload, $eventId),
                self::EVENT_CHARGE_REFUNDED =>
                    $this->handleChargeRefunded($payload, $eventId),
                self::EVENT_DISPUTE_CREATED =>
                    $this->handleDisputeCreated($payload, $eventId),
                self::EVENT_DISPUTE_CLOSED =>
                    $this->handleDisputeClosed($payload, $eventId),
                default => false,
            };

            $event->forceFill([
                'status' => $handled
                    ? PaymentWebhookEvent::STATUS_PROCESSED
                    : PaymentWebhookEvent::STATUS_IGNORED,
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
        $session = $this->resource($payload, 'checkout');

        /* Browser redirects never clear TradeX; a verified paid webhook does. */
        if (
            strtolower(trim((string) ($session['payment_status'] ?? '')))
            !== 'paid'
        ) {
            return false;
        }

        $orderUuid = trim((string) (
            $session['client_reference_id']
            ?? data_get($session, 'metadata.tradex_order_uuid', '')
        ));
        $sessionId = trim((string) ($session['id'] ?? ''));
        $paymentIntentId = trim((string) ($session['payment_intent'] ?? ''));

        if ($orderUuid === '' || $sessionId === '' || $paymentIntentId === '') {
            throw new AuthApiException(
                422,
                'STRIPE_WEBHOOK_RESOURCE_INVALID',
                'Stripe checkout settlement references are incomplete.'
            );
        }

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->where('provider', PaymentOrder::PROVIDER_STRIPE)
            ->first();

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        $this->assertSessionReference($order, $sessionId);

        $this->settlement->settle(
            order: $order,
            provider: PaymentOrder::PROVIDER_STRIPE,
            providerPaymentReference: $paymentIntentId,
            currency: strtoupper(trim((string) ($session['currency'] ?? ''))),
            grossMinor: (int) ($session['amount_total'] ?? 0),
            providerEventId: $eventId
        );

        return true;
    }

    private function handleCheckoutExpired(array $payload, string $eventId): bool
    {
        $session = $this->resource($payload, 'checkout');
        $orderUuid = trim((string) (
            $session['client_reference_id']
            ?? data_get($session, 'metadata.tradex_order_uuid', '')
        ));
        $sessionId = trim((string) ($session['id'] ?? ''));

        if ($orderUuid === '' || $sessionId === '') {
            throw new AuthApiException(
                422,
                'STRIPE_WEBHOOK_RESOURCE_INVALID',
                'Stripe checkout expiration references are incomplete.'
            );
        }

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->where('provider', PaymentOrder::PROVIDER_STRIPE)
            ->first();

        if (! $order instanceof PaymentOrder) {
            return false;
        }

        $this->assertSessionReference($order, $sessionId);

        DB::transaction(function () use ($order, $eventId): void {
            $locked = PaymentOrder::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->isCleared()
                || $locked->isPaymentReversed()
                || Payment::query()
                    ->where('payment_order_id', $locked->getKey())
                    ->exists()
                || ! in_array(
                    $locked->status,
                    [
                        PaymentOrder::STATUS_CREATED,
                        PaymentOrder::STATUS_AWAITING_USER_ACTION,
                    ],
                    true
                )
            ) {
                return;
            }

            $locked->forceFill([
                'status' => PaymentOrder::STATUS_CANCELLED,
                'failure_code' => 'STRIPE_CHECKOUT_SESSION_EXPIRED',
            ])->save();

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'STRIPE_CHECKOUT_SESSION_EXPIRED',
                module: 'PAYMENTS',
                targetType: 'PAYMENT_ORDER',
                targetId: $locked->getKey(),
                newValues: [
                    'payment_order_uuid' => $locked->uuid,
                    'provider_event_recorded' => $eventId !== '',
                ]
            );
        }, 3);

        return true;
    }

    private function handleChargeRefunded(array $payload, string $eventId): bool
    {
        $charge = $this->resource($payload, 'refund');
        $paymentIntentId = trim((string) ($charge['payment_intent'] ?? ''));

        if ($paymentIntentId === '') {
            return false;
        }

        $payment = $this->paymentForIntent($paymentIntentId);

        if (! $payment instanceof Payment) {
            return false;
        }

        $currency = strtoupper(trim((string) ($charge['currency'] ?? '')));
        $chargeAmount = (int) ($charge['amount'] ?? 0);
        $refundedAmount = (int) ($charge['amount_refunded'] ?? 0);

        $this->assertMoneyResourceMatchesPayment(
            $payment,
            $currency,
            $chargeAmount,
            $refundedAmount,
            'STRIPE_REFUND_AMOUNT_MISMATCH'
        );

        $isFullRefund = (bool) ($charge['refunded'] ?? false)
            && $refundedAmount === (int) $payment->gross_amount_minor;

        if (! $isFullRefund) {
            $this->recordReviewRequired(
                $payment,
                'STRIPE_PARTIAL_REFUND_REVIEW_REQUIRED',
                $eventId,
                ['refunded_amount_minor' => $refundedAmount]
            );

            return true;
        }

        $this->reversals->recordStripeProcessorReversal(
            payment: $payment,
            reason: 'Stripe reported a full processor refund for the cleared payment.',
            providerEventId: $eventId
        );

        return true;
    }

    private function handleDisputeCreated(array $payload, string $eventId): bool
    {
        $dispute = $this->resource($payload, 'dispute');
        $payment = $this->paymentFromDispute($dispute);

        if (! $payment instanceof Payment) {
            return false;
        }

        $this->assertDisputeMatchesPayment($payment, $dispute);
        $this->recordReviewRequired(
            $payment,
            'STRIPE_DISPUTE_OPENED_REVIEW_REQUIRED',
            $eventId,
            [
                'dispute_status' => trim((string) ($dispute['status'] ?? '')),
                'dispute_reason' => trim((string) ($dispute['reason'] ?? '')),
                'dispute_amount_minor' => (int) ($dispute['amount'] ?? 0),
            ]
        );

        return true;
    }

    private function handleDisputeClosed(array $payload, string $eventId): bool
    {
        $dispute = $this->resource($payload, 'dispute');
        $payment = $this->paymentFromDispute($dispute);

        if (! $payment instanceof Payment) {
            return false;
        }

        $this->assertDisputeMatchesPayment($payment, $dispute);

        $status = strtolower(trim((string) ($dispute['status'] ?? '')));
        $amount = (int) ($dispute['amount'] ?? 0);

        if (
            $status === 'lost'
            && $amount === (int) $payment->gross_amount_minor
        ) {
            $this->reversals->recordStripeProcessorReversal(
                payment: $payment,
                reason: 'Stripe reported a lost full-payment dispute for the cleared payment.',
                providerEventId: $eventId
            );

            return true;
        }

        $this->recordReviewRequired(
            $payment,
            $status === 'lost'
                ? 'STRIPE_PARTIAL_DISPUTE_LOST_REVIEW_REQUIRED'
                : 'STRIPE_DISPUTE_CLOSED_NO_REVERSAL',
            $eventId,
            [
                'dispute_status' => $status,
                'dispute_amount_minor' => $amount,
            ]
        );

        return true;
    }

    private function resource(array $payload, string $kind): array
    {
        $resource = data_get($payload, 'data.object');

        if (! is_array($resource)) {
            throw new AuthApiException(
                422,
                'STRIPE_WEBHOOK_RESOURCE_INVALID',
                'Stripe '.$kind.' resource is invalid.'
            );
        }

        return $resource;
    }

    private function paymentFromDispute(array $dispute): ?Payment
    {
        return $this->paymentForIntent(
            trim((string) ($dispute['payment_intent'] ?? ''))
        );
    }

    private function paymentForIntent(string $paymentIntentId): ?Payment
    {
        $paymentIntentId = trim($paymentIntentId);

        if ($paymentIntentId === '') {
            return null;
        }

        return Payment::query()
            ->where('provider', PaymentOrder::PROVIDER_STRIPE)
            ->where(
                'provider_capture_reference_hash',
                hash(
                    'sha256',
                    PaymentOrder::PROVIDER_STRIPE.'|'.$paymentIntentId
                )
            )
            ->first();
    }

    private function assertDisputeMatchesPayment(
        Payment $payment,
        array $dispute
    ): void {
        $currency = strtoupper(trim((string) ($dispute['currency'] ?? '')));
        $amount = (int) ($dispute['amount'] ?? 0);

        if (
            $currency === ''
            || $currency !== strtoupper((string) $payment->currency)
            || $amount <= 0
            || $amount > (int) $payment->gross_amount_minor
        ) {
            throw new AuthApiException(
                409,
                'STRIPE_DISPUTE_AMOUNT_MISMATCH',
                'The Stripe dispute does not match the cleared TradeX payment.'
            );
        }
    }

    private function assertMoneyResourceMatchesPayment(
        Payment $payment,
        string $currency,
        int $originalAmount,
        int $reversedAmount,
        string $errorCode
    ): void {
        if (
            $currency === ''
            || $currency !== strtoupper((string) $payment->currency)
            || $originalAmount !== (int) $payment->gross_amount_minor
            || $reversedAmount <= 0
            || $reversedAmount > $originalAmount
        ) {
            throw new AuthApiException(
                409,
                $errorCode,
                'The Stripe reversal amount does not match the cleared TradeX payment.'
            );
        }
    }

    private function assertSessionReference(
        PaymentOrder $order,
        string $sessionId
    ): void {
        if (
            $order->provider_reference !== null
            && ! hash_equals((string) $order->provider_reference, $sessionId)
        ) {
            throw new AuthApiException(
                409,
                'STRIPE_CHECKOUT_REFERENCE_CONFLICT',
                'Stripe checkout session does not match the TradeX order.'
            );
        }
    }

    private function recordReviewRequired(
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
                'payment_uuid' => $payment->uuid,
                'provider' => PaymentOrder::PROVIDER_STRIPE,
                'provider_event_recorded' => $providerEventId !== '',
                'manual_review_required' => true,
                ...$context,
            ]
        );
    }

    private function verifySignature(string $payload, string $header): void
    {
        $secret = trim((string) config(
            'tradex_integrations.stripe.webhook_secret'
        ));

        if ($secret === '' || $header === '') {
            throw new AuthApiException(
                401,
                'STRIPE_WEBHOOK_SIGNATURE_INVALID',
                'Stripe webhook signature is missing or cannot be verified.'
            );
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(
                explode('=', trim($part), 2),
                2,
                ''
            );

            if ($key === 't') {
                $timestamp = ctype_digit($value) ? (int) $value : null;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        $tolerance = max(
            60,
            (int) config(
                'tradex_integrations.stripe.webhook_tolerance_seconds',
                300
            )
        );

        if (
            $timestamp === null
            || abs(time() - $timestamp) > $tolerance
            || $signatures === []
        ) {
            throw new AuthApiException(
                401,
                'STRIPE_WEBHOOK_SIGNATURE_INVALID',
                'Stripe webhook signature is outside the accepted verification window.'
            );
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp.'.'.$payload,
            $secret
        );

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return;
            }
        }

        throw new AuthApiException(
            401,
            'STRIPE_WEBHOOK_SIGNATURE_INVALID',
            'Stripe webhook signature could not be verified.'
        );
    }

    private function present(
        PaymentWebhookEvent $event,
        bool $duplicate
    ): array {
        return [
            'webhook_event_uuid' => $event->uuid,
            'event_type' => $event->event_type,
            'status' => $event->status,
            'duplicate' => $duplicate,
            'processed_at' => $event->processed_at?->toISOString(),
        ];
    }
}
