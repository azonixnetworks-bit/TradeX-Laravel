<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class StripeNowPaymentsTransactionApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_new_gateway_create_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertUnauthorized();

        $this->postJson('/api/v1/payments/nowpayments/invoices')
            ->assertUnauthorized();
    }

    public function test_stripe_requires_runtime_and_admin_approval(): void
    {
        $this->configureStripe();
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/payments/methods')
            ->assertOk()
            ->assertJsonPath('data.methods.1.code', PaymentOrder::PROVIDER_STRIPE)
            ->assertJsonPath('data.methods.1.available', false);

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'stripe-unapproved-0001')
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertUnprocessable();
    }

    public function test_stripe_checkout_creation_is_idempotent_and_returns_only_redirect_reference(): void
    {
        $this->configureStripe(true);
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_tradex_21',
                'url' => 'https://checkout.stripe.test/c/pay/cs_test_tradex_21',
            ], 200),
        ]);

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'stripe-checkout-00000001')
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertCreated()
            ->assertJsonPath('data.provider', PaymentOrder::PROVIDER_STRIPE)
            ->assertJsonPath('data.status', PaymentOrder::STATUS_AWAITING_USER_ACTION)
            ->assertJsonPath('data.approval_url', 'https://checkout.stripe.test/c/pay/cs_test_tradex_21');

        $uuid = (string) $first->json('data.uuid');

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'stripe-checkout-00000001')
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertCreated()
            ->assertJsonPath('data.uuid', $uuid);

        $this->assertDatabaseCount('payment_orders', 1);
        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($uuid): bool {
            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request->hasHeader('Authorization', 'Bearer sk_test_api21')
                && data_get($request->data(), 'client_reference_id') === $uuid
                && (int) data_get($request->data(), 'line_items.0.price_data.unit_amount') === 7900;
        });
    }

    public function test_verified_paid_stripe_webhook_clears_order_and_activates_premium(): void
    {
        $this->configureStripe(true);
        $user = $this->createApiUser();
        $order = $this->gatewayOrder($user->getKey(), PaymentOrder::PROVIDER_STRIPE, 'cs_test_settle_21');

        $payload = [
            'id' => 'evt_api21_stripe_paid',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_settle_21',
                    'payment_status' => 'paid',
                    'client_reference_id' => $order->uuid,
                    'payment_intent' => 'pi_api21_settle_21',
                    'currency' => 'usd',
                    'amount_total' => 7900,
                    'metadata' => ['tradex_order_uuid' => $order->uuid],
                ],
            ],
        ];

        $response = $this->postRawJson(
            '/api/v1/payments/stripe/webhook',
            $payload,
            ['HTTP_STRIPE_SIGNATURE' => $this->stripeSignature($payload)]
        );

        $response->assertOk()
            ->assertJsonPath('data.status', 'PROCESSED')
            ->assertJsonPath('data.duplicate', false);

        $this->assertDatabaseHas('payment_orders', [
            'id' => $order->getKey(),
            'status' => PaymentOrder::STATUS_CLEARED,
        ]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentOrder::PROVIDER_STRIPE, $payment->provider);
        $this->assertSame(Payment::AFFILIATE_NOT_APPLICABLE, $payment->affiliate_processing_status);
        $this->assertNull($payment->processor_fee_minor);
        $this->assertNull($payment->net_eligible_revenue_minor);

        $this->assertSame('PREMIUM', $user->fresh()->account_type);
        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'status' => UserEntitlement::STATUS_ACTIVE,
        ]);
    }

    public function test_stripe_webhook_rejects_invalid_signature_without_settlement(): void
    {
        $this->configureStripe(true);
        $user = $this->createApiUser();
        $order = $this->gatewayOrder($user->getKey(), PaymentOrder::PROVIDER_STRIPE, 'cs_test_invalid_sig');

        $payload = [
            'id' => 'evt_invalid_sig',
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_test_invalid_sig',
                'payment_status' => 'paid',
                'client_reference_id' => $order->uuid,
                'payment_intent' => 'pi_invalid_sig',
                'currency' => 'usd',
                'amount_total' => 7900,
            ]],
        ];

        $this->postRawJson(
            '/api/v1/payments/stripe/webhook',
            $payload,
            ['HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=invalid']
        )->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('FREE', $user->fresh()->account_type);
    }

    public function test_nowpayments_invoice_creation_is_idempotent_and_uses_hosted_invoice(): void
    {
        $this->configureNowPayments(true);
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        Http::fake([
            'https://api-sandbox.nowpayments.io/v1/invoice' => Http::response([
                'id' => 'np_invoice_api21',
                'invoice_url' => 'https://invoice.nowpayments.test/np_invoice_api21',
            ], 200),
        ]);

        $first = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'nowpayments-invoice-0001')
            ->postJson('/api/v1/payments/nowpayments/invoices')
            ->assertCreated()
            ->assertJsonPath('data.provider', PaymentOrder::PROVIDER_NOWPAYMENTS)
            ->assertJsonPath('data.approval_url', 'https://invoice.nowpayments.test/np_invoice_api21');

        $uuid = (string) $first->json('data.uuid');

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'nowpayments-invoice-0001')
            ->postJson('/api/v1/payments/nowpayments/invoices')
            ->assertCreated()
            ->assertJsonPath('data.uuid', $uuid);

        $this->assertDatabaseCount('payment_orders', 1);
        Http::assertSentCount(1);
        Http::assertSent(function (HttpRequest $request) use ($uuid): bool {
            return $request->url() === 'https://api-sandbox.nowpayments.io/v1/invoice'
                && $request->hasHeader('x-api-key', 'now-api-api21')
                && (string) data_get($request->data(), 'order_id') === $uuid
                && (float) data_get($request->data(), 'price_amount') === 79.0
                && data_get($request->data(), 'price_currency') === 'usd';
        });
    }

    public function test_finished_nowpayments_ipn_clears_order_and_activates_premium(): void
    {
        $this->configureNowPayments(true);
        $user = $this->createApiUser();
        $order = $this->gatewayOrder($user->getKey(), PaymentOrder::PROVIDER_NOWPAYMENTS, 'np_invoice_finish_21');

        $payload = [
            'payment_id' => 'np_payment_finish_21',
            'payment_status' => 'finished',
            'order_id' => $order->uuid,
            'price_amount' => 79,
            'price_currency' => 'usd',
            'pay_amount' => 79,
            'pay_currency' => 'usdttrc20',
        ];

        $this->postRawJson(
            '/api/v1/payments/nowpayments/webhook',
            $payload,
            ['HTTP_X_NOWPAYMENTS_SIG' => $this->nowPaymentsSignature($payload)]
        )->assertOk()
            ->assertJsonPath('data.status', 'PROCESSED');

        $this->assertDatabaseHas('payment_orders', [
            'id' => $order->getKey(),
            'status' => PaymentOrder::STATUS_CLEARED,
        ]);
        $this->assertDatabaseHas('payments', [
            'payment_order_id' => $order->getKey(),
            'provider' => PaymentOrder::PROVIDER_NOWPAYMENTS,
            'status' => Payment::STATUS_CLEARED,
        ]);
        $this->assertSame('PREMIUM', $user->fresh()->account_type);
    }

    public function test_partially_paid_nowpayments_ipn_never_activates_premium(): void
    {
        $this->configureNowPayments(true);
        $user = $this->createApiUser();
        $order = $this->gatewayOrder($user->getKey(), PaymentOrder::PROVIDER_NOWPAYMENTS, 'np_invoice_partial_21');

        $payload = [
            'payment_id' => 'np_payment_partial_21',
            'payment_status' => 'partially_paid',
            'order_id' => $order->uuid,
            'price_amount' => 79,
            'price_currency' => 'usd',
            'actually_paid' => 40,
        ];

        $this->postRawJson(
            '/api/v1/payments/nowpayments/webhook',
            $payload,
            ['HTTP_X_NOWPAYMENTS_SIG' => $this->nowPaymentsSignature($payload)]
        )->assertOk()
            ->assertJsonPath('data.status', 'PROCESSED');

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('FREE', $user->fresh()->account_type);
        $this->assertDatabaseHas('payment_orders', [
            'id' => $order->getKey(),
            'status' => PaymentOrder::STATUS_PROCESSING,
            'failure_code' => 'NOWPAYMENTS_PARTIALLY_PAID_REVIEW',
        ]);
    }

    public function test_nowpayments_ipn_rejects_invalid_signature_without_state_change(): void
    {
        $this->configureNowPayments(true);
        $user = $this->createApiUser();
        $order = $this->gatewayOrder($user->getKey(), PaymentOrder::PROVIDER_NOWPAYMENTS, 'np_invoice_bad_sig');

        $payload = [
            'payment_id' => 'np_payment_bad_sig',
            'payment_status' => 'finished',
            'order_id' => $order->uuid,
            'price_amount' => 79,
            'price_currency' => 'usd',
        ];

        $this->postRawJson(
            '/api/v1/payments/nowpayments/webhook',
            $payload,
            ['HTTP_X_NOWPAYMENTS_SIG' => 'invalid']
        )->assertUnauthorized();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(PaymentOrder::STATUS_AWAITING_USER_ACTION, $order->fresh()->status);
        $this->assertSame('FREE', $user->fresh()->account_type);
    }

    private function configureStripe(bool $approved = false): void
    {
        config()->set('tradex_integrations.stripe.enabled', true);
        config()->set('tradex_integrations.stripe.countries', ['LK']);
        config()->set('tradex_integrations.stripe.environment', 'SANDBOX');
        config()->set('tradex_integrations.stripe.publishable_key', 'pk_test_api21');
        config()->set('tradex_integrations.stripe.secret_key', 'sk_test_api21');
        config()->set('tradex_integrations.stripe.webhook_secret', 'whsec_api21');
        config()->set('tradex_integrations.stripe.base_url', 'https://api.stripe.com');
        config()->set('tradex_integrations.stripe.success_url', 'https://app.tradex.test/payments/success');
        config()->set('tradex_integrations.stripe.cancel_url', 'https://app.tradex.test/payments/cancel');

        if ($approved) {
            $this->approveProvider(PaymentOrder::PROVIDER_STRIPE);
        }
    }

    private function configureNowPayments(bool $approved = false): void
    {
        config()->set('tradex_integrations.nowpayments.enabled', true);
        config()->set('tradex_integrations.nowpayments.countries', ['LK']);
        config()->set('tradex_integrations.nowpayments.environment', 'SANDBOX');
        config()->set('tradex_integrations.nowpayments.api_key', 'now-api-api21');
        config()->set('tradex_integrations.nowpayments.ipn_secret', 'now-ipn-api21');
        config()->set('tradex_integrations.nowpayments.sandbox_base_url', 'https://api-sandbox.nowpayments.io');
        config()->set('tradex_integrations.nowpayments.success_url', 'https://app.tradex.test/payments/success');
        config()->set('tradex_integrations.nowpayments.cancel_url', 'https://app.tradex.test/payments/cancel');

        if ($approved) {
            $this->approveProvider(PaymentOrder::PROVIDER_NOWPAYMENTS);
        }
    }

    private function approveProvider(string $provider): void
    {
        IntegrationProviderState::query()->create([
            'provider_code' => $provider,
            'is_enabled' => true,
            'environment' => 'SANDBOX',
            'countries' => ['LK'],
            'eligibility_confirmed' => true,
            'version' => 1,
        ]);
    }

    private function gatewayOrder(int $userId, string $provider, string $reference): PaymentOrder
    {
        return PaymentOrder::query()->create([
            'user_id' => $userId,
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => $provider,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'client_idempotency_key_hash' => hash('sha256', (string) Str::uuid()),
            'provider_reference' => $reference,
            'expires_at' => now()->addDay(),
        ]);
    }

    private function stripeSignature(array $payload): string
    {
        $timestamp = time();
        $raw = $this->rawJson($payload);
        $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_api21');

        return 't='.$timestamp.',v1='.$signature;
    }

    private function nowPaymentsSignature(array $payload): string
    {
        $sorted = $this->sortRecursive($payload);
        $encoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return hash_hmac('sha512', $encoded, 'now-ipn-api21');
    }

    private function postRawJson(string $uri, array $payload, array $server = [])
    {
        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $server),
            $this->rawJson($payload)
        );
    }

    private function rawJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
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
}
