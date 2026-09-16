<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\UserEntitlement;
use App\Services\Payments\PaymentReversalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class DynamicProductPayPalTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_custom_product_can_be_bought_with_paypal_and_activates_its_own_entitlement(): void
    {
        $this->configurePayPal();

        $product = Product::query()->create([
            'code' => 'TRADEX_SIGNAL_PACK',
            'name' => 'TradeX Signal Pack',
            'description' => 'Lifetime signal pack access.',
            'billing_type' => Product::BILLING_ONE_TIME_LIFETIME,
            'currency' => 'USD',
            'amount_minor' => 12900,
            'is_active' => true,
        ]);

        $buyer = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken($buyer);

        Http::fakeSequence()
            ->push([
                'access_token' => 'paypal-access-token',
            ], 200)
            ->push([
                'id' => 'PAYPAL-ORDER-DYNAMIC-1',
                'status' => 'CREATED',
                'links' => [
                    [
                        'rel' => 'approve',
                        'href' => 'https://paypal.example/approve/dynamic-1',
                    ],
                ],
            ], 201)
            ->push([
                'access_token' => 'paypal-access-token',
            ], 200)
            ->push([
                'id' => 'PAYPAL-ORDER-DYNAMIC-1',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'id' => 'PAYPAL-CAPTURE-DYNAMIC-1',
                                    'status' => 'COMPLETED',
                                    'amount' => [
                                        'currency_code' => 'USD',
                                        'value' => '129.00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 201);

        $created = $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'dynamic-product-paypal-0001'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders',
                [
                    'product_uuid' => $product->uuid,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.amount.amount_minor',
                12900
            );

        $orderUuid = (string) $created->json('data.uuid');

        $this->assertDatabaseHas(
            'payment_orders',
            [
                'uuid' => $orderUuid,
                'user_id' => $buyer->getKey(),
                'product_code' => 'TRADEX_SIGNAL_PACK',
                'provider' => PaymentOrder::PROVIDER_PAYPAL,
                'currency' => 'USD',
                'amount_minor' => 12900,
            ]
        );

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/payments/paypal/orders/'
                .$orderUuid
                .'/capture'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_CLEARED
            );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' => $buyer->getKey(),
                'product_code' => 'TRADEX_SIGNAL_PACK',
                'status' => UserEntitlement::STATUS_ACTIVE,
            ]
        );

        $buyer->refresh();

        $this->assertNotSame(
            'PREMIUM',
            $buyer->account_type,
            'A generic product must not mutate the legacy Education Premium account type.'
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonFragment([
                'uuid' => $product->uuid,
                'code' => 'TRADEX_SIGNAL_PACK',
                'purchase_allowed' => false,
            ]);

        Http::fake();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'dynamic-product-paypal-repeat-0002'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders',
                ['product_uuid' => $product->uuid]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'PRODUCT_ALREADY_OWNED'
            );

        Http::assertSentCount(4);

        $order = PaymentOrder::query()
            ->where('uuid', $orderUuid)
            ->firstOrFail();

        $payment = Payment::query()
            ->where('payment_order_id', $order->getKey())
            ->firstOrFail();

        app(PaymentReversalService::class)
            ->recordPayPalProcessorReversal($payment);

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' => $buyer->getKey(),
                'product_code' => 'TRADEX_SIGNAL_PACK',
                'status' => UserEntitlement::STATUS_REVOKED,
                'payment_id' => $payment->getKey(),
            ]
        );
    }

    public function test_same_idempotency_key_cannot_be_reused_for_another_product(): void
    {
        $this->configurePayPal();

        $first = Product::query()->create([
            'code' => 'TRADEX_PRODUCT_A',
            'name' => 'TradeX Product A',
            'billing_type' => Product::BILLING_ONE_TIME_LIFETIME,
            'currency' => 'USD',
            'amount_minor' => 4900,
            'is_active' => true,
        ]);

        $second = Product::query()->create([
            'code' => 'TRADEX_PRODUCT_B',
            'name' => 'TradeX Product B',
            'billing_type' => Product::BILLING_ONE_TIME_LIFETIME,
            'currency' => 'USD',
            'amount_minor' => 5900,
            'is_active' => true,
        ]);

        $buyer = $this->createApiUser([
            'country_code' => 'LK',
        ]);
        $token = $this->issueBoundToken($buyer);

        Http::fakeSequence()
            ->push(['access_token' => 'paypal-access-token'], 200)
            ->push([
                'id' => 'PAYPAL-ORDER-IDEMPOTENCY',
                'status' => 'CREATED',
                'links' => [[
                    'rel' => 'approve',
                    'href' => 'https://paypal.example/approve/idempotency',
                ]],
            ], 201);

        $key = 'dynamic-product-paypal-same-key-0001';

        $this
            ->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(
                '/api/v1/payments/paypal/orders',
                ['product_uuid' => $first->uuid]
            )
            ->assertCreated();

        $this
            ->withToken($token)
            ->withHeader('Idempotency-Key', $key)
            ->postJson(
                '/api/v1/payments/paypal/orders',
                ['product_uuid' => $second->uuid]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'IDEMPOTENCY_KEY_CONFLICT'
            );
    }

    public function test_inactive_custom_product_cannot_start_paypal_checkout(): void
    {
        $this->configurePayPal();

        $product = Product::query()->create([
            'code' => 'TRADEX_FUTURE_PRODUCT',
            'name' => 'TradeX Future Product',
            'billing_type' => Product::BILLING_ONE_TIME_LIFETIME,
            'currency' => 'USD',
            'amount_minor' => 9900,
            'is_active' => false,
        ]);

        $buyer = $this->createApiUser([
            'country_code' => 'LK',
        ]);
        $token = $this->issueBoundToken($buyer);

        Http::fake();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'inactive-product-paypal-0001'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders',
                ['product_uuid' => $product->uuid]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PRODUCT_NOT_PURCHASABLE'
            );

        Http::assertNothingSent();
    }

    private function configurePayPal(): void
    {
        config()->set('tradex_payments.paypal.driver', 'http');
        config()->set('tradex_payments.paypal.enabled', true);
        config()->set('tradex_payments.paypal.countries', ['LK', 'IN']);
        config()->set('tradex_payments.paypal.environment', 'sandbox');
        config()->set('tradex_payments.paypal.client_id', 'paypal-client');
        config()->set('tradex_payments.paypal.client_secret', 'paypal-secret');
        config()->set('tradex_payments.paypal.webhook_id', 'paypal-webhook-id');
        config()->set(
            'tradex_payments.paypal.sandbox_base_url',
            'https://api-m.sandbox.paypal.com'
        );
        config()->set(
            'tradex_payments.paypal.live_base_url',
            'https://api-m.paypal.com'
        );
        config()->set(
            'tradex_payments.paypal.return_url',
            'https://app.tradex.test/payments/paypal/return'
        );
        config()->set(
            'tradex_payments.paypal.cancel_url',
            'https://app.tradex.test/payments/paypal/cancel'
        );

        IntegrationProviderState::query()->create([
            'provider_code' => PaymentOrder::PROVIDER_PAYPAL,
            'is_enabled' => true,
            'environment' => 'SANDBOX',
            'countries' => ['LK', 'IN'],
            'eligibility_confirmed' => true,
            'version' => 1,
        ]);
    }
}
