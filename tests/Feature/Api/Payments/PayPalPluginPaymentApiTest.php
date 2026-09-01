<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\UserEntitlement;
use App\Services\Payments\PayPalPluginClientFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PayPalPluginPaymentApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_srmklive_driver_creates_digital_course_order_and_completed_capture_activates_premium(): void
    {
        $this->configurePluginPayPal();

        $buyer = $this->createApiUser([
            'email' =>
                'buyer-paypal-plugin@example.test',
            'country_code' =>
                'LK',
        ]);

        $token = $this->issueBoundToken(
            $buyer
        );

        $client = Mockery::mock(
            PayPalClient::class
        );

        $client->shouldReceive(
            'withIdempotencyKey'
        )
            ->twice()
            ->andReturnSelf();

        $client->shouldReceive(
            'setRequestHeader'
        )
            ->twice()
            ->with(
                'Prefer',
                'return=representation'
            )
            ->andReturnSelf();

        $client->shouldReceive(
            'createOrder'
        )
            ->once()
            ->with(
                Mockery::on(
                    function (
                        array $payload
                    ): bool {
                        return data_get(
                            $payload,
                            'intent'
                        ) === 'CAPTURE'
                            && data_get(
                                $payload,
                                'purchase_units.0.amount.currency_code'
                            ) === 'USD'
                            && data_get(
                                $payload,
                                'purchase_units.0.amount.value'
                            ) === '79.00'
                            && data_get(
                                $payload,
                                'purchase_units.0.items.0.name'
                            ) === 'TradeX Education Premium'
                            && data_get(
                                $payload,
                                'purchase_units.0.items.0.sku'
                            ) === PaymentOrder::PRODUCT_EDUCATION_PREMIUM
                            && data_get(
                                $payload,
                                'purchase_units.0.items.0.category'
                            ) === 'DIGITAL_GOODS'
                            && data_get(
                                $payload,
                                'application_context.shipping_preference'
                            ) === 'NO_SHIPPING'
                            && data_get(
                                $payload,
                                'application_context.user_action'
                            ) === 'PAY_NOW';
                    }
                )
            )
            ->andReturn([
                'id' =>
                    'PAYPAL-PLUGIN-ORDER-001',
                'status' =>
                    'CREATED',
                'links' => [
                    [
                        'rel' =>
                            'approve',
                        'href' =>
                            'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-PLUGIN-ORDER-001',
                    ],
                ],
            ]);

        $client->shouldReceive(
            'capturePaymentOrder'
        )
            ->once()
            ->with(
                'PAYPAL-PLUGIN-ORDER-001'
            )
            ->andReturn([
                'id' =>
                    'PAYPAL-PLUGIN-ORDER-001',
                'status' =>
                    'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'id' =>
                                        'PAYPAL-PLUGIN-CAPTURE-001',
                                    'status' =>
                                        'COMPLETED',
                                    'amount' => [
                                        'currency_code' =>
                                            'USD',
                                        'value' =>
                                            '79.00',
                                    ],
                                    'seller_receivable_breakdown' => [
                                        'paypal_fee' => [
                                            'currency_code' =>
                                                'USD',
                                            'value' =>
                                                '3.00',
                                        ],
                                        'net_amount' => [
                                            'currency_code' =>
                                                'USD',
                                            'value' =>
                                                '76.00',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $factory = Mockery::mock(
            PayPalPluginClientFactory::class
        );

        $factory->shouldReceive(
            'authenticated'
        )
            ->twice()
            ->andReturn(
                $client
            );

        $this->app->instance(
            PayPalPluginClientFactory::class,
            $factory
        );

        $created = $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'plugin-paypal-order-0001'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders'
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.provider',
                PaymentOrder::PROVIDER_PAYPAL
            )
            ->assertJsonPath(
                'data.status',
                PaymentOrder::STATUS_AWAITING_USER_ACTION
            )
            ->assertJsonPath(
                'data.amount.amount_minor',
                7900
            );

        $orderUuid = (string) $created
            ->json('data.uuid');

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
            )
            ->assertJsonPath(
                'data.payment.status',
                Payment::STATUS_CLEARED
            )
            ->assertJsonPath(
                'data.payment.gross.amount_minor',
                7900
            )
            ->assertJsonPath(
                'data.payment.processor_fee.amount_minor',
                300
            )
            ->assertJsonPath(
                'data.payment.net_eligible_revenue.amount_minor',
                7600
            );

        $buyer->refresh();

        $this->assertSame(
            'PREMIUM',
            $buyer->account_type
        );

        $this->assertNotNull(
            $buyer->premium_activated_at
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' =>
                    $buyer->getKey(),
                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
            ]
        );
    }

    private function configurePluginPayPal(): void
    {
        config()->set(
            'tradex_payments.paypal.driver',
            'srmklive'
        );

        config()->set(
            'tradex_payments.paypal.enabled',
            true
        );

        config()->set(
            'tradex_payments.paypal.countries',
            ['LK', 'IN']
        );

        config()->set(
            'tradex_payments.paypal.environment',
            'sandbox'
        );

        config()->set(
            'tradex_payments.paypal.webhook_id',
            'paypal-plugin-webhook-id'
        );

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

        config()->set(
            'tradex_payments.product.education_premium.paypal.amount_minor',
            7900
        );

        config()->set(
            'tradex_payments.product.education_premium.paypal.currency',
            'USD'
        );

        config()->set(
            'paypal.mode',
            'sandbox'
        );

        config()->set(
            'paypal.sandbox.client_id',
            'sandbox-client-id'
        );

        config()->set(
            'paypal.sandbox.client_secret',
            'sandbox-client-secret'
        );

        IntegrationProviderState::query()
            ->create([
                'provider_code' =>
                    PaymentOrder::PROVIDER_PAYPAL,

                'is_enabled' =>
                    true,

                'environment' =>
                    'SANDBOX',

                'countries' =>
                    ['LK', 'IN'],

                'eligibility_confirmed' =>
                    true,

                'version' =>
                    1,
            ]);
    }
}
