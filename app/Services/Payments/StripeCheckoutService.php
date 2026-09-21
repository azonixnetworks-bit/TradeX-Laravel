<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class StripeCheckoutService
{
    public function __construct(
        private readonly PaymentMethodService $methods,
        private readonly PaymentOrderService $orders,
        private readonly PaymentApiPresenter $presenter,
        private readonly StripeConfigurationSafety $stripeConfiguration
    ) {}

    public function create(User $user, string $idempotencyKey, Request $request): array
    {
        $method = $this->methods->assertAvailable(
            $user,
            PaymentOrder::PROVIDER_STRIPE
        );

        /*
         * Defense in depth: Checkout refuses to send a secret key anywhere
         * unless the environment/key/endpoint contract is safe here too.
         */
        $this->stripeConfiguration->assertConfigured();

        $hash = $this->orders->idempotencyHash($idempotencyKey);
        $existing = PaymentOrder::query()
            ->where('user_id', $user->getKey())
            ->where('client_idempotency_key_hash', $hash)
            ->first();

        if ($existing instanceof PaymentOrder) {
            if ($existing->provider !== PaymentOrder::PROVIDER_STRIPE) {
                throw new AuthApiException(
                    409,
                    'PAYMENT_IDEMPOTENCY_CONFLICT',
                    'The idempotency key is already used by another payment provider.'
                );
            }

            return $this->presenter->order($existing);
        }

        $amount = (int) data_get($method, 'amount.amount_minor', 0);
        $currency = strtoupper((string) data_get(
            $method,
            'amount.currency',
            'USD'
        ));

        /* Keep a margin below Stripe Checkout's maximum expiry window. */
        $expiresAt = now()->addHours(23);

        $order = PaymentOrder::query()->create([
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'country_code' => strtoupper((string) $user->country_code),
            'currency' => $currency,
            'amount_minor' => $amount,
            'status' => PaymentOrder::STATUS_CREATED,
            'client_idempotency_key_hash' => $hash,
            'expires_at' => $expiresAt,
        ]);

        try {
            $response = Http::asForm()
                ->withToken(trim((string) config(
                    'tradex_integrations.stripe.secret_key'
                )))
                ->acceptJson()
                ->timeout((int) config(
                    'tradex_integrations.stripe.timeout_seconds',
                    15
                ))
                ->withHeaders([
                    'Idempotency-Key' => 'tradex-'.$order->uuid,
                ])
                ->post($this->baseUrl().'/v1/checkout/sessions', [
                    'mode' => 'payment',
                    'client_reference_id' => $order->uuid,
                    'success_url' => (string) config(
                        'tradex_integrations.stripe.success_url'
                    ),
                    'cancel_url' => (string) config(
                        'tradex_integrations.stripe.cancel_url'
                    ),

                    /*
                     * Phase 1 intentionally limits Checkout to cards. The
                     * browser return is never treated as proof of payment.
                     */
                    'payment_method_types' => ['card'],
                    'expires_at' => $expiresAt->getTimestamp(),
                    'line_items' => [[
                        'quantity' => 1,
                        'price_data' => [
                            'currency' => strtolower($currency),
                            'unit_amount' => $amount,
                            'product_data' => [
                                'name' => (string) config(
                                    'tradex_payments.product.education_premium.name',
                                    'TradeX Education Premium'
                                ),
                            ],
                        ],
                    ]],
                    'metadata' => [
                        'tradex_order_uuid' => $order->uuid,
                        'tradex_product_code' => $order->product_code,
                    ],
                    'payment_intent_data' => [
                        'metadata' => [
                            'tradex_order_uuid' => $order->uuid,
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            $this->markFailed($order, 'STRIPE_CONNECTION_FAILED');

            throw new AuthApiException(
                503,
                'STRIPE_UNAVAILABLE',
                'Stripe is temporarily unavailable.'
            );
        }

        if (! $response->successful()) {
            $this->markFailed($order, 'STRIPE_CHECKOUT_CREATE_FAILED');

            throw new AuthApiException(
                502,
                'STRIPE_CHECKOUT_CREATE_FAILED',
                'Stripe could not create the checkout session.'
            );
        }

        $sessionId = trim((string) $response->json('id'));
        $checkoutUrl = trim((string) $response->json('url'));

        if (
            $sessionId === ''
            || $checkoutUrl === ''
            || ! str_starts_with($sessionId, 'cs_')
            || filter_var($checkoutUrl, FILTER_VALIDATE_URL) === false
            || strtolower((string) parse_url(
                $checkoutUrl,
                PHP_URL_SCHEME
            )) !== 'https'
            || ! $this->checkoutHostIsAllowed($checkoutUrl)
        ) {
            $this->markFailed($order, 'STRIPE_CHECKOUT_RESPONSE_INVALID');

            throw new AuthApiException(
                502,
                'STRIPE_CHECKOUT_RESPONSE_INVALID',
                'Stripe returned an incomplete checkout response.'
            );
        }

        $order->forceFill([
            'provider_reference' => $sessionId,
            'approval_url' => $checkoutUrl,
            'status' => PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'failure_code' => null,
        ])->save();

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'STRIPE_CHECKOUT_SESSION_CREATED',
            module: 'PAYMENTS',
            targetType: 'PAYMENT_ORDER',
            targetId: $order->getKey(),
            newValues: [
                'payment_order_uuid' => $order->uuid,
                'provider' => PaymentOrder::PROVIDER_STRIPE,
                'amount_minor' => $amount,
                'currency' => $currency,
                'checkout_expires_at' => $expiresAt->toISOString(),
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->presenter->order($order->fresh());
    }

    private function baseUrl(): string
    {
        return rtrim((string) config(
            'tradex_integrations.stripe.base_url',
            'https://api.stripe.com'
        ), '/');
    }

    private function checkoutHostIsAllowed(string $url): bool
    {
        if (strtoupper((string) config('tradex_integrations.stripe.environment')) !== 'LIVE') {
            return true;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'stripe.com' || str_ends_with($host, '.stripe.com');
    }

    private function markFailed(PaymentOrder $order, string $code): void
    {
        $order->forceFill([
            'status' => PaymentOrder::STATUS_FAILED,
            'failure_code' => $code,
        ])->save();
    }
}
