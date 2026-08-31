<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class StripeProductionHardeningTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_no_voluntary_refund_route_is_exposed(): void
    {
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            $uri = strtolower((string) $route->uri());
            $name = strtolower((string) ($route->getName() ?? ''));

            $this->assertStringNotContainsString('refund', $uri);
            $this->assertStringNotContainsString('refund', $name);
        }
    }

    public function test_live_runtime_rejects_test_keys_before_any_stripe_request_is_sent(): void
    {
        $this->configureStripe(
            approved: true,
            environment: 'LIVE',
            publishableKey: 'pk_test_wrong_for_live',
            secretKey: 'sk_test_wrong_for_live'
        );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        Http::fake();

        $this->withToken($token)
            ->withHeader('Idempotency-Key', 'stripe-live-key-safety-0001')
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_orders', 0);
    }

    public function test_checkout_uses_official_endpoint_card_only_and_matching_expiry(): void
    {
        $this->configureStripe(approved: true);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_prod_hardening_01',
                'url' => 'https://checkout.stripe.test/c/pay/cs_test_prod_hardening_01',
            ], 200),
        ]);

        $before = now()->addHours(23)->getTimestamp();

        $response = $this->withToken($token)
            ->withHeader('Idempotency-Key', 'stripe-prod-hardening-0001')
            ->postJson('/api/v1/payments/stripe/checkout-sessions')
            ->assertCreated()
            ->assertJsonPath('data.provider', PaymentOrder::PROVIDER_STRIPE)
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_AWAITING_USER_ACTION
            );

        $after = now()->addHours(23)->getTimestamp();
        $orderUuid = (string) $response->json('data.uuid');

        Http::assertSent(function (HttpRequest $request) use (
            $orderUuid,
            $before,
            $after
        ): bool {
            $expiresAt = (int) data_get($request->data(), 'expires_at', 0);

            return $request->url()
                    === 'https://api.stripe.com/v1/checkout/sessions'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer sk_test_prod_hardening'
                )
                && data_get($request->data(), 'mode') === 'payment'
                && data_get($request->data(), 'client_reference_id')
                    === $orderUuid
                && data_get($request->data(), 'payment_method_types.0')
                    === 'card'
                && $expiresAt >= $before
                && $expiresAt <= $after;
        });

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->firstOrFail();

        $this->assertNotNull($order->expires_at);
        $this->assertSame(
            PaymentOrder::STATUS_AWAITING_USER_ACTION,
            $order->status
        );
    }

    public function test_verified_expired_checkout_cancels_only_unpaid_order(): void
    {
        $this->configureStripe(approved: true);

        $user = $this->createApiUser();
        $order = $this->gatewayOrder(
            $user,
            'cs_test_expired_prod_hardening'
        );

        $payload = [
            'id' => 'evt_stripe_checkout_expired_prod_hardening',
            'type' => 'checkout.session.expired',
            'data' => [
                'object' => [
                    'id' => 'cs_test_expired_prod_hardening',
                    'client_reference_id' => $order->uuid,
                    'metadata' => [
                        'tradex_order_uuid' => $order->uuid,
                    ],
                ],
            ],
        ];

        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            );

        $this->assertDatabaseHas('payment_orders', [
            'id' => $order->getKey(),
            'status' => PaymentOrder::STATUS_CANCELLED,
            'failure_code' => 'STRIPE_CHECKOUT_SESSION_EXPIRED',
        ]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('FREE', $user->fresh()->account_type);
    }

    public function test_open_dispute_never_automatically_revokes_premium(): void
    {
        $this->configureStripe(approved: true);

        [$user, $order, $payment, $entitlement] =
            $this->clearedStripePremiumPayment('pi_dispute_open_prod_hardening');

        $payload = [
            'id' => 'evt_stripe_dispute_open_prod_hardening',
            'type' => 'charge.dispute.created',
            'data' => [
                'object' => [
                    'id' => 'dp_prod_hardening_open',
                    'payment_intent' => 'pi_dispute_open_prod_hardening',
                    'status' => 'warning_needs_response',
                    'reason' => 'fraudulent',
                    'currency' => 'usd',
                    'amount' => 7900,
                ],
            ],
        ];

        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            );

        $this->assertSame(
            Payment::STATUS_CLEARED,
            $payment->fresh()->status
        );
        $this->assertSame(
            PaymentOrder::STATUS_CLEARED,
            $order->fresh()->status
        );
        $this->assertSame(
            UserEntitlement::STATUS_ACTIVE,
            $entitlement->fresh()->status
        );
        $this->assertSame('PREMIUM', $user->fresh()->account_type);
        $this->assertDatabaseCount('payment_adjustments', 0);
    }

    public function test_full_lost_dispute_forces_exact_tied_payment_reversal_idempotently(): void
    {
        $this->configureStripe(approved: true);

        [$user, $order, $payment, $entitlement] =
            $this->clearedStripePremiumPayment('pi_dispute_lost_prod_hardening');

        $payload = [
            'id' => 'evt_stripe_dispute_lost_prod_hardening',
            'type' => 'charge.dispute.closed',
            'data' => [
                'object' => [
                    'id' => 'dp_prod_hardening_lost',
                    'payment_intent' => 'pi_dispute_lost_prod_hardening',
                    'status' => 'lost',
                    'reason' => 'fraudulent',
                    'currency' => 'usd',
                    'amount' => 7900,
                ],
            ],
        ];

        $first = $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            )
            ->assertJsonPath('data.duplicate', false);

        $eventUuid = (string) $first->json('data.webhook_event_uuid');

        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath('data.webhook_event_uuid', $eventUuid)
            ->assertJsonPath('data.duplicate', true);

        $this->assertSame(
            Payment::STATUS_PAYMENT_REVERSED,
            $payment->fresh()->status
        );
        $this->assertSame(
            Payment::REVERSAL_STRIPE_PROCESSOR,
            $payment->fresh()->payment_reversal_code
        );
        $this->assertSame(
            PaymentOrder::STATUS_PAYMENT_REVERSED,
            $order->fresh()->status
        );
        $this->assertSame(
            UserEntitlement::STATUS_REVOKED,
            $entitlement->fresh()->status
        );
        $this->assertSame('FREE', $user->fresh()->account_type);
        $this->assertDatabaseCount('payment_adjustments', 1);
        $this->assertDatabaseCount('payment_webhook_events', 1);
    }

    public function test_external_full_stripe_reversal_event_reconciles_ledger_without_exposing_refund_api(): void
    {
        $this->configureStripe(approved: true);

        [$user, $order, $payment, $entitlement] =
            $this->clearedStripePremiumPayment('pi_external_reversal_prod_hardening');

        $payload = [
            'id' => 'evt_stripe_external_reversal_prod_hardening',
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id' => 'ch_external_reversal_prod_hardening',
                    'payment_intent' => 'pi_external_reversal_prod_hardening',
                    'currency' => 'usd',
                    'amount' => 7900,
                    'amount_refunded' => 7900,
                    'refunded' => true,
                ],
            ],
        ];

        $this->postStripeWebhook($payload)
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            );

        $this->assertSame(
            Payment::STATUS_PAYMENT_REVERSED,
            $payment->fresh()->status
        );
        $this->assertSame(
            PaymentOrder::STATUS_PAYMENT_REVERSED,
            $order->fresh()->status
        );
        $this->assertSame(
            UserEntitlement::STATUS_REVOKED,
            $entitlement->fresh()->status
        );
        $this->assertSame('FREE', $user->fresh()->account_type);
        $this->assertDatabaseCount('payment_adjustments', 1);
    }

    private function configureStripe(
        bool $approved = false,
        string $environment = 'SANDBOX',
        ?string $publishableKey = null,
        ?string $secretKey = null
    ): void {
        $environment = strtoupper($environment);

        config()->set('tradex_integrations.stripe.enabled', true);
        config()->set('tradex_integrations.stripe.countries', ['LK']);
        config()->set('tradex_integrations.stripe.environment', $environment);
        config()->set(
            'tradex_integrations.stripe.publishable_key',
            $publishableKey
                ?? ($environment === 'LIVE'
                    ? 'pk_live_prod_hardening'
                    : 'pk_test_prod_hardening')
        );
        config()->set(
            'tradex_integrations.stripe.secret_key',
            $secretKey
                ?? ($environment === 'LIVE'
                    ? 'sk_live_prod_hardening'
                    : 'sk_test_prod_hardening')
        );
        config()->set(
            'tradex_integrations.stripe.webhook_secret',
            'whsec_prod_hardening'
        );
        config()->set(
            'tradex_integrations.stripe.base_url',
            'https://api.stripe.com'
        );
        config()->set(
            'tradex_integrations.stripe.success_url',
            'https://app.tradex.test/payments/success'
        );
        config()->set(
            'tradex_integrations.stripe.cancel_url',
            'https://app.tradex.test/payments/cancel'
        );

        if ($approved) {
            IntegrationProviderState::query()->create([
                'provider_code' => PaymentOrder::PROVIDER_STRIPE,
                'is_enabled' => true,
                'environment' => $environment,
                'countries' => ['LK'],
                'eligibility_confirmed' => true,
                'version' => 1,
            ]);
        }
    }

    private function gatewayOrder(
        User $user,
        string $sessionId
    ): PaymentOrder {
        return PaymentOrder::query()->create([
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'client_idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'provider_reference' => $sessionId,
            'expires_at' => now()->addHours(23),
        ]);
    }

    private function clearedStripePremiumPayment(string $paymentIntentId): array
    {
        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
            'education_premium_entitlement' => false,
            'premium_activated_at' => now(),
        ]);

        $order = PaymentOrder::query()->create([
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'country_code' => 'LK',
            'currency' => 'USD',
            'amount_minor' => 7900,
            'status' => PaymentOrder::STATUS_CLEARED,
            'client_idempotency_key_hash' => hash(
                'sha256',
                (string) Str::uuid()
            ),
            'provider_reference' => 'cs_'.Str::lower(Str::random(24)),
            'cleared_at' => now(),
            'expires_at' => now()->addHours(23),
        ]);

        $payment = Payment::query()->create([
            'payment_order_id' => $order->getKey(),
            'user_id' => $user->getKey(),
            'provider' => PaymentOrder::PROVIDER_STRIPE,
            'currency' => 'USD',
            'gross_amount_minor' => 7900,
            'processor_fee_minor' => null,
            'net_eligible_revenue_minor' => null,
            'provider_capture_reference_hash' => hash(
                'sha256',
                PaymentOrder::PROVIDER_STRIPE.'|'.$paymentIntentId
            ),
            'provider_capture_reference' => $paymentIntentId,
            'status' => Payment::STATUS_CLEARED,
            'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
            'cleared_at' => now(),
        ]);

        $entitlement = UserEntitlement::query()->create([
            'user_id' => $user->getKey(),
            'product_code' => PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
            'status' => UserEntitlement::STATUS_ACTIVE,
            'activated_at' => now(),
            'payment_id' => $payment->getKey(),
        ]);

        return [$user, $order, $payment, $entitlement];
    }

    private function postStripeWebhook(array $payload)
    {
        $raw = $this->rawJson($payload);
        $timestamp = time();
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$raw,
            'whsec_prod_hardening'
        );

        return $this->call(
            'POST',
            '/api/v1/payments/stripe/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' =>
                    't='.$timestamp.',v1='.$signature,
            ],
            $raw
        );
    }

    private function rawJson(array $payload): string
    {
        return json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
        );
    }
}
