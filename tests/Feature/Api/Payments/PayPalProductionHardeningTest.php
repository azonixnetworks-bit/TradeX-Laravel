<?php

namespace Tests\Feature\Api\Payments;

use App\Models\IntegrationProviderState;
use App\Models\Payment;
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

class PayPalProductionHardeningTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_paypal_requires_runtime_admin_approval_even_when_credentials_are_present(): void
    {
        $this->configurePayPal(
            approved: false
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        Http::fake();

        $methods = $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/methods'
            )
            ->assertOk();

        $paypal = collect(
            $methods->json(
                'data.methods'
            )
        )->firstWhere(
            'code',
            PaymentOrder::PROVIDER_PAYPAL
        );

        $this->assertIsArray(
            $paypal
        );

        $this->assertFalse(
            (bool) (
                $paypal['available']
                ?? true
            )
        );

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'paypal-runtime-gate-0001'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders'
            )
            ->assertUnprocessable();

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'payment_orders',
            0
        );
    }

    public function test_global_paypal_wildcard_scope_allows_an_admin_approved_customer_country(): void
    {
        $this->configurePayPal(
            approved: true,
            countries: ['*']
        );

        $user = $this->createApiUser([
            'country_code' => 'AE',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        Http::fakeSequence()
            ->push([
                'access_token' =>
                    'paypal-access-token',
            ], 200)
            ->push([
                'id' =>
                    'PAYPAL-GLOBAL-ORDER-001',

                'status' =>
                    'CREATED',

                'links' => [
                    [
                        'rel' =>
                            'approve',

                        'href' =>
                            'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-GLOBAL-ORDER-001',
                    ],
                ],
            ], 201);

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'paypal-global-scope-0001'
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
                'data.country_code',
                'AE'
            );
    }

    public function test_live_paypal_runtime_rejects_a_non_official_api_host_before_sending_credentials(): void
    {
        $this->configurePayPal(
            approved: true,
            environment: 'LIVE',
            liveBaseUrl:
                'https://malicious.example.test'
        );

        $user = $this->createApiUser([
            'country_code' => 'LK',
        ]);

        $token = $this->issueBoundToken(
            $user
        );

        Http::fake();

        $this
            ->withToken($token)
            ->withHeader(
                'Idempotency-Key',
                'paypal-live-host-safety-0001'
            )
            ->postJson(
                '/api/v1/payments/paypal/orders'
            )
            ->assertUnprocessable();

        Http::assertNothingSent();

        $this->assertDatabaseCount(
            'payment_orders',
            0
        );
    }

    public function test_webhook_postback_preserves_the_original_raw_event_json(): void
    {
        $this->configurePayPal(
            approved: false
        );

        $raw = '{"id":"WH-RAW-001","event_type":"UNHANDLED.TEST","resource":{"note":"a/b","n":1}}';

        $preserved = false;

        Http::fake(
            function (
                HttpRequest $request
            ) use (
                &$preserved,
                $raw
            ) {
                if (
                    str_ends_with(
                        $request->url(),
                        '/v1/oauth2/token'
                    )
                ) {
                    return Http::response([
                        'access_token' =>
                            'paypal-token',
                    ], 200);
                }

                if (
                    str_ends_with(
                        $request->url(),
                        '/v1/notifications/verify-webhook-signature'
                    )
                ) {
                    $preserved =
                        str_contains(
                            $request->body(),
                            '"webhook_event":'
                            .$raw
                        );

                    return Http::response([
                        'verification_status' =>
                            'SUCCESS',
                    ], 200);
                }

                return Http::response(
                    [],
                    500
                );
            }
        );

        $server = [
            'CONTENT_TYPE' =>
                'application/json',

            'HTTP_ACCEPT' =>
                'application/json',

            'HTTP_PAYPAL_AUTH_ALGO' =>
                'SHA256withRSA',

            'HTTP_PAYPAL_CERT_URL' =>
                'https://api.paypal.com/cert',

            'HTTP_PAYPAL_TRANSMISSION_ID' =>
                (string) Str::uuid(),

            'HTTP_PAYPAL_TRANSMISSION_SIG' =>
                'signature',

            'HTTP_PAYPAL_TRANSMISSION_TIME' =>
                now()->toIso8601String(),
        ];

        $this->call(
            'POST',
            '/api/v1/payments/paypal/webhook',
            [],
            [],
            [],
            $server,
            $raw
        )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_IGNORED
            );

        $this->assertTrue(
            $preserved
        );
    }

    public function test_partial_external_paypal_refund_requires_review_and_never_auto_revokes_premium(): void
    {
        $this->configurePayPal();

        [
            $user,
            $order,
            $payment,
            $entitlement,
        ] = $this->clearedPayPalPayment(
            'PAYPAL-CAPTURE-PARTIAL-001'
        );

        $this->fakeWebhookVerification();

        $this->postWebhook([
            'id' =>
                'WH-PAYPAL-PARTIAL-REFUND-001',

            'event_type' =>
                'PAYMENT.CAPTURE.REFUNDED',

            'resource' => [
                'id' =>
                    'PAYPAL-CAPTURE-PARTIAL-001',

                'status' =>
                    'PARTIALLY_REFUNDED',

                'amount' => [
                    'currency_code' =>
                        'USD',

                    'value' =>
                        '79.00',
                ],
            ],
        ])
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

        $this->assertSame(
            'PREMIUM',
            $user->fresh()->account_type
        );

        $this->assertDatabaseCount(
            'payment_adjustments',
            0
        );
    }

    public function test_full_external_paypal_refund_reverses_only_the_exact_tied_payment_idempotently(): void
    {
        $this->configurePayPal();

        [
            $user,
            $order,
            $payment,
            $entitlement,
        ] = $this->clearedPayPalPayment(
            'PAYPAL-CAPTURE-FULL-001'
        );

        $this->fakeWebhookVerification(
            2
        );

        $payload = [
            'id' =>
                'WH-PAYPAL-FULL-REFUND-001',

            'event_type' =>
                'PAYMENT.CAPTURE.REFUNDED',

            'resource' => [
                'id' =>
                    'PAYPAL-CAPTURE-FULL-001',

                'status' =>
                    'REFUNDED',

                'amount' => [
                    'currency_code' =>
                        'USD',

                    'value' =>
                        '79.00',
                ],
            ],
        ];

        $first = $this
            ->postWebhook(
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            )
            ->assertJsonPath(
                'data.duplicate',
                false
            );

        $eventUuid =
            (string) $first->json(
                'data.webhook_event_uuid'
            );

        $this->postWebhook(
            $payload
        )
            ->assertOk()
            ->assertJsonPath(
                'data.webhook_event_uuid',
                $eventUuid
            )
            ->assertJsonPath(
                'data.duplicate',
                true
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

        $this->assertSame(
            'FREE',
            $user->fresh()->account_type
        );

        $this->assertDatabaseCount(
            'payment_adjustments',
            1
        );

        $this->assertDatabaseCount(
            'payment_webhook_events',
            1
        );
    }

    public function test_open_paypal_dispute_never_automatically_revokes_premium(): void
    {
        $this->configurePayPal();

        [
            $user,
            $order,
            $payment,
            $entitlement,
        ] = $this->clearedPayPalPayment(
            'PAYPAL-CAPTURE-DISPUTE-001'
        );

        $this->fakeWebhookVerification();

        $this->postWebhook([
            'id' =>
                'WH-PAYPAL-DISPUTE-001',

            'event_type' =>
                'CUSTOMER.DISPUTE.CREATED',

            'resource' => [
                'dispute_id' =>
                    'PP-D-TEST-001',

                'status' =>
                    'WAITING_FOR_SELLER_RESPONSE',

                'reason' =>
                    'UNAUTHORISED',

                'disputed_transactions' => [
                    [
                        'seller_transaction_id' =>
                            'PAYPAL-CAPTURE-DISPUTE-001',
                    ],
                ],
            ],
        ])
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

        $this->assertSame(
            'PREMIUM',
            $user->fresh()->account_type
        );
    }

    public function test_denied_capture_cannot_create_payment_or_entitlement(): void
    {
        $this->configurePayPal();

        $user = $this->createApiUser([
            'country_code' =>
                'LK',
        ]);

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $user->getKey(),

                    'product_code' =>
                        PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                    'provider' =>
                        PaymentOrder::PROVIDER_PAYPAL,

                    'country_code' =>
                        'LK',

                    'currency' =>
                        'USD',

                    'amount_minor' =>
                        7900,

                    'status' =>
                        PaymentOrder::STATUS_AWAITING_USER_ACTION,

                    'client_idempotency_key_hash' =>
                        hash(
                            'sha256',
                            (string) Str::uuid()
                        ),

                    'provider_reference' =>
                        'PAYPAL-DENIED-ORDER-001',
                ]);

        $this->fakeWebhookVerification();

        $this->postWebhook([
            'id' =>
                'WH-PAYPAL-DENIED-001',

            'event_type' =>
                'PAYMENT.CAPTURE.DENIED',

            'resource' => [
                'id' =>
                    'PAYPAL-DENIED-CAPTURE-001',

                'status' =>
                    'DECLINED',

                'custom_id' =>
                    $order->uuid,

                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' =>
                            'PAYPAL-DENIED-ORDER-001',
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            );

        $this->assertSame(
            PaymentOrder::STATUS_FAILED,
            $order->fresh()->status
        );

        $this->assertDatabaseCount(
            'payments',
            0
        );

        $this->assertDatabaseCount(
            'user_entitlements',
            0
        );

        $this->assertSame(
            'FREE',
            $user->fresh()->account_type
        );
    }

    public function test_no_paypal_refund_initiation_route_is_exposed(): void
    {
        foreach (
            app('router')
                ->getRoutes()
                ->getRoutes()
            as $route
        ) {
            $uri = strtolower(
                (string) $route->uri()
            );

            if (
                ! str_contains(
                    $uri,
                    'payments/paypal'
                )
            ) {
                continue;
            }

            $name = strtolower(
                (string) (
                    $route->getName()
                    ?? ''
                )
            );

            $this->assertStringNotContainsString(
                'refund',
                $uri
            );

            $this->assertStringNotContainsString(
                'refund',
                $name
            );
        }
    }

    private function configurePayPal(
        bool $approved = false,
        string $environment = 'SANDBOX',
        array $countries = ['LK'],
        ?string $liveBaseUrl = null
    ): void {
        $environment = strtoupper(
            $environment
        );

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
            $countries
        );

        config()->set(
            'tradex_payments.paypal.environment',
            strtolower(
                $environment
            )
        );

        config()->set(
            'tradex_payments.paypal.client_id',
            'paypal-prod-hardening-client'
        );

        config()->set(
            'tradex_payments.paypal.client_secret',
            'paypal-prod-hardening-secret'
        );

        config()->set(
            'tradex_payments.paypal.webhook_id',
            'paypal-prod-hardening-webhook'
        );

        config()->set(
            'tradex_payments.paypal.sandbox_base_url',
            'https://api-m.sandbox.paypal.com'
        );

        config()->set(
            'tradex_payments.paypal.live_base_url',
            $liveBaseUrl
                ?? 'https://api-m.paypal.com'
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

        if ($approved) {
            IntegrationProviderState::query()
                ->create([
                    'provider_code' =>
                        PaymentOrder::PROVIDER_PAYPAL,

                    'is_enabled' =>
                        true,

                    'environment' =>
                        $environment,

                    'countries' =>
                        $countries,

                    'eligibility_confirmed' =>
                        true,

                    'version' =>
                        1,
                ]);
        }
    }

    private function clearedPayPalPayment(
        string $captureId
    ): array {
        $user = $this->createApiUser([
            'account_type' =>
                'PREMIUM',

            'education_premium_entitlement' =>
                false,

            'premium_activated_at' =>
                now(),
        ]);

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $user->getKey(),

                    'product_code' =>
                        PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                    'provider' =>
                        PaymentOrder::PROVIDER_PAYPAL,

                    'country_code' =>
                        'LK',

                    'currency' =>
                        'USD',

                    'amount_minor' =>
                        7900,

                    'status' =>
                        PaymentOrder::STATUS_CLEARED,

                    'client_idempotency_key_hash' =>
                        hash(
                            'sha256',
                            (string) Str::uuid()
                        ),

                    'provider_reference' =>
                        'PAYPAL-ORDER-'
                        .Str::upper(
                            Str::random(10)
                        ),

                    'cleared_at' =>
                        now(),
                ]);

        $payment =
            Payment::query()
                ->create([
                    'payment_order_id' =>
                        $order->getKey(),

                    'user_id' =>
                        $user->getKey(),

                    'provider' =>
                        PaymentOrder::PROVIDER_PAYPAL,

                    'currency' =>
                        'USD',

                    'gross_amount_minor' =>
                        7900,

                    'processor_fee_minor' =>
                        300,

                    'net_eligible_revenue_minor' =>
                        7600,

                    'provider_capture_reference_hash' =>
                        hash(
                            'sha256',
                            $captureId
                        ),

                    'provider_capture_reference' =>
                        $captureId,

                    'status' =>
                        Payment::STATUS_CLEARED,

                    'affiliate_processing_status' =>
                        Payment::AFFILIATE_NOT_APPLICABLE,

                    'cleared_at' =>
                        now(),
                ]);

        $entitlement =
            UserEntitlement::query()
                ->create([
                    'user_id' =>
                        $user->getKey(),

                    'product_code' =>
                        PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                    'status' =>
                        UserEntitlement::STATUS_ACTIVE,

                    'activated_at' =>
                        now(),

                    'payment_id' =>
                        $payment->getKey(),
                ]);

        return [
            $user,
            $order,
            $payment,
            $entitlement,
        ];
    }

    private function fakeWebhookVerification(
        int $deliveries = 1
    ): void {
        $sequence = Http::fakeSequence();

        for (
            $index = 0;
            $index < $deliveries;
            $index++
        ) {
            $sequence
                ->push([
                    'access_token' =>
                        'paypal-token',
                ], 200)
                ->push([
                    'verification_status' =>
                        'SUCCESS',
                ], 200);
        }
    }

    private function postWebhook(
        array $payload
    ) {
        return $this
            ->withHeaders(
                $this->webhookHeaders()
            )
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload
            );
    }

    private function webhookHeaders(): array
    {
        return [
            'PAYPAL-AUTH-ALGO' =>
                'SHA256withRSA',

            'PAYPAL-CERT-URL' =>
                'https://api.paypal.com/cert',

            'PAYPAL-TRANSMISSION-ID' =>
                (string) Str::uuid(),

            'PAYPAL-TRANSMISSION-SIG' =>
                'signature',

            'PAYPAL-TRANSMISSION-TIME' =>
                now()->toIso8601String(),
        ];
    }
}