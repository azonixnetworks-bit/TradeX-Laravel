<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class NowPaymentsInvoiceService
{
    public function __construct(
        private readonly PaymentMethodService $methods,
        private readonly PaymentOrderService $orders,
        private readonly PaymentApiPresenter $presenter
    ) {
    }

    public function create(User $user, string $idempotencyKey, Request $request): array
    {
        $method = $this->methods->assertAvailable($user, PaymentOrder::PROVIDER_NOWPAYMENTS);
        $hash = $this->orders->idempotencyHash($idempotencyKey);
        $existing = PaymentOrder::query()
            ->where('user_id', $user->getKey())
            ->where('client_idempotency_key_hash', $hash)
            ->first();

        if ($existing instanceof PaymentOrder) {
            if ($existing->provider !== PaymentOrder::PROVIDER_NOWPAYMENTS) {
                throw new AuthApiException(409, 'PAYMENT_IDEMPOTENCY_CONFLICT', 'The idempotency key is already used by another payment provider.');
            }
            return $this->presenter->order($existing);
        }

        $amountMinor = (int) data_get($method, 'amount.amount_minor', 0);
        $currency = strtoupper((string) data_get($method, 'amount.currency', 'USD'));
        $amountDecimal = (string) data_get($method, 'amount.amount', '0.00');

        $order = PaymentOrder::query()->create([
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_NOWPAYMENTS,
            'country_code' => strtoupper((string) $user->country_code),
            'currency' => $currency,
            'amount_minor' => $amountMinor,
            'status' => PaymentOrder::STATUS_CREATED,
            'client_idempotency_key_hash' => $hash,
            'expires_at' => now()->addDays(7),
        ]);

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-api-key' => (string) config('tradex_integrations.nowpayments.api_key')])
                ->timeout((int) config('tradex_integrations.nowpayments.timeout_seconds', 15))
                ->post($this->baseUrl().'/v1/invoice', [
                    'price_amount' => (float) $amountDecimal,
                    'price_currency' => strtolower($currency),
                    'order_id' => $order->uuid,
                    'order_description' => (string) config('tradex_payments.product.education_premium.name', 'TradeX Education Premium'),
                    'ipn_callback_url' => route('api.v1.payments.nowpayments.webhook'),
                    'success_url' => (string) config('tradex_integrations.nowpayments.success_url'),
                    'cancel_url' => (string) config('tradex_integrations.nowpayments.cancel_url'),
                    'is_fixed_rate' => true,
                ]);
        } catch (ConnectionException) {
            $this->markFailed($order, 'NOWPAYMENTS_CONNECTION_FAILED');
            throw new AuthApiException(503, 'NOWPAYMENTS_UNAVAILABLE', 'NOWPayments is temporarily unavailable.');
        }

        if (! $response->successful()) {
            $this->markFailed($order, 'NOWPAYMENTS_INVOICE_CREATE_FAILED');
            throw new AuthApiException(502, 'NOWPAYMENTS_INVOICE_CREATE_FAILED', 'NOWPayments could not create the invoice.');
        }

        $invoiceId = trim((string) $response->json('id'));
        $invoiceUrl = trim((string) $response->json('invoice_url'));
        if ($invoiceId === '' || $invoiceUrl === '') {
            $this->markFailed($order, 'NOWPAYMENTS_INVOICE_RESPONSE_INVALID');
            throw new AuthApiException(502, 'NOWPAYMENTS_INVOICE_RESPONSE_INVALID', 'NOWPayments returned an incomplete invoice response.');
        }

        $order->forceFill([
            'provider_reference' => $invoiceId,
            'approval_url' => $invoiceUrl,
            'status' => PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'failure_code' => null,
        ])->save();

        AuditLog::record(
            actorType: 'USER', actorId: $user->getKey(), action: 'NOWPAYMENTS_INVOICE_CREATED',
            module: 'PAYMENTS', targetType: 'PAYMENT_ORDER', targetId: $order->getKey(),
            newValues: [
                'payment_order_uuid' => $order->uuid,
                'provider' => PaymentOrder::PROVIDER_NOWPAYMENTS,
                'amount_minor' => $amountMinor,
                'currency' => $currency,
            ],
            ipAddress: $request->ip(), userAgent: $request->userAgent()
        );

        return $this->presenter->order($order->fresh());
    }

    private function baseUrl(): string
    {
        $environment = strtoupper((string) config('tradex_integrations.nowpayments.environment', 'SANDBOX'));
        $url = $environment === 'LIVE'
            ? config('tradex_integrations.nowpayments.live_base_url', 'https://api.nowpayments.io')
            : config('tradex_integrations.nowpayments.sandbox_base_url');
        if (! filled($url)) {
            throw new AuthApiException(503, 'NOWPAYMENTS_BASE_URL_NOT_CONFIGURED', 'NOWPayments runtime endpoint is not configured.');
        }
        return rtrim((string) $url, '/');
    }

    private function markFailed(PaymentOrder $order, string $code): void
    {
        $order->forceFill(['status' => PaymentOrder::STATUS_FAILED, 'failure_code' => $code])->save();
    }
}
