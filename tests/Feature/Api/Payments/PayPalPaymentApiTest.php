<?php

namespace Tests\Feature\Api\Payments;

use App\Models\AffiliateCommission;
use App\Models\AffiliateProfile;
use App\Models\IntegrationProviderState;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\UserEntitlement;
use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PayPalPaymentApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_paypal_create_is_idempotent_and_completed_capture_activates_premium_and_affiliate_commission(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $this->configurePayPal();

        $affiliate = $this->createApiUser([
            'email' =>
                'affiliate-paypal@example.test',
        ]);

        $buyer = $this->createApiUser([
            'email' =>
                'buyer-paypal@example.test',

            'referred_by_user_id' =>
                $affiliate->getKey(),
        ]);

        $token = $this->issueBoundToken(
            $buyer
        );

        Http::fakeSequence()
            ->push([
                'access_token' =>
                    'paypal-access-token',
            ], 200)
            ->push([
                'id' =>
                    'PAYPAL-ORDER-API12',

                'status' =>
                    'CREATED',

                'links' => [
                    [
                        'rel' =>
                            'approve',

                        'href' =>
                            'https://paypal.example/approve/API12',
                    ],
                ],
            ], 201)
            ->push([
                'access_token' =>
                    'paypal-access-token',
            ], 200)
            ->push([
                'id' =>
                    'PAYPAL-ORDER-API12',

                'status' =>
                    'COMPLETED',

                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'id' =>
                                        'PAYPAL-CAPTURE-API12',

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
            ], 201);

        $idempotencyKey =
            'api12-paypal-idempotency-0001';

        $created = $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                $idempotencyKey
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
            ->withHeader(
                'Idempotency-Key',
                $idempotencyKey
            )
            ->postJson(
                '/api/v1/payments/paypal/orders'
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.uuid',
                $orderUuid
            );

        $this->assertSame(
            1,
            PaymentOrder::query()
                ->where(
                    'user_id',
                    $buyer->getKey()
                )
                ->count()
        );

        $captured = $this
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
            )
            ->assertJsonPath(
                'data.payment.affiliate_processing_status',
                Payment::AFFILIATE_PROCESSED
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

        $profile =
            AffiliateProfile::query()
                ->where(
                    'user_id',
                    $affiliate->getKey()
                )
                ->firstOrFail();

        $this->assertSame(
            1,
            (int) $profile
                ->qualified_sales_count
        );

        $commission =
            AffiliateCommission::query()
                ->where(
                    'affiliate_user_id',
                    $affiliate->getKey()
                )
                ->firstOrFail();

        $this->assertSame(
            7600,
            (int) $commission
                ->calculation_base_cents
        );

        $this->assertSame(
            1140,
            (int) $commission
                ->commission_amount_cents
        );

        $payload = json_encode(
            $captured->json('data'),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'PAYPAL-CAPTURE-API12',
            $payload
        );

        $this->assertNoInternalIdKey(
            $captured->json('data')
        );

        Http::assertSent(
            function (
                HttpClientRequest $request
            ) use (
                $orderUuid
            ): bool {
                return $request->method() === 'POST'
                    && $request->url()
                        === 'https://api-m.sandbox.paypal.com'
                        .'/v2/checkout/orders/'
                        .'PAYPAL-ORDER-API12'
                        .'/capture'
                    && $request->body() === '{}'
                    && $request->hasHeader(
                        'PayPal-Request-Id',
                        'tradex-capture-'.$orderUuid
                    );
            }
        );

        Http::assertSentCount(
            4
        );
    }

    private function configurePayPal(): void
    {
        config()->set(
            'tradex_payments.paypal.driver',
            'http'
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
            'tradex_payments.paypal.client_id',
            'paypal-client'
        );

        config()->set(
            'tradex_payments.paypal.client_secret',
            'paypal-secret'
        );

        config()->set(
            'tradex_payments.paypal.webhook_id',
            'paypal-webhook-id'
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

    private function assertNoInternalIdKey(
        mixed $value
    ): void {
        if (! is_array($value)) {
            return;
        }

        foreach (
            $value as $key => $nested
        ) {
            if (is_string($key)) {
                $this->assertNotSame(
                    'id',
                    $key,
                    'Payment API must not expose internal numeric id fields.'
                );
            }

            $this->assertNoInternalIdKey(
                $nested
            );
        }
    }
}
