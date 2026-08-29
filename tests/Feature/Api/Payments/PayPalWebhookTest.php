<?php

namespace Tests\Feature\Api\Payments;

use App\Models\AffiliateQualifiedSale;
use App\Models\Payment;
use App\Models\PaymentAdjustment;
use App\Models\PaymentOrder;
use App\Models\PaymentWebhookEvent;
use App\Models\UserEntitlement;
use App\Services\Affiliates\AffiliateCommercialService;
use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_verified_completed_webhook_settles_order_and_duplicate_event_is_idempotent(): void
    {
        $this->configureWebhook();

        $user = $this->createApiUser([
            'account_type' =>
                'FREE',
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
                        'PAYPAL-WEBHOOK-ORDER-1',
                ]);

        Http::fakeSequence()
            ->push([
                'access_token' =>
                    'paypal-token',
            ], 200)
            ->push([
                'verification_status' =>
                    'SUCCESS',
            ], 200)
            ->push([
                'access_token' =>
                    'paypal-token',
            ], 200)
            ->push([
                'verification_status' =>
                    'SUCCESS',
            ], 200);

        $payload = [
            'id' =>
                'WH-EVENT-COMPLETED-API12',

            'event_type' =>
                'PAYMENT.CAPTURE.COMPLETED',

            'resource' => [
                'id' =>
                    'PAYPAL-WEBHOOK-CAPTURE-1',

                'status' =>
                    'COMPLETED',

                'custom_id' =>
                    $order->uuid,

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

                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' =>
                            'PAYPAL-WEBHOOK-ORDER-1',
                    ],
                ],
            ],
        ];

        $headers = $this
            ->webhookHeaders();

        $first = $this
            ->withHeaders($headers)
            ->postJson(
                '/api/v1/payments/paypal/webhook',
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
            $first->json(
                'data.webhook_event_uuid'
            );

        $this
            ->withHeaders($headers)
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                $payload
            )
            ->assertOk()
            ->assertJsonPath(
                'data.webhook_event_uuid',
                $eventUuid
            )
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            )
            ->assertJsonPath(
                'data.duplicate',
                true
            );

        $this->assertDatabaseCount(
            'payment_webhook_events',
            1
        );

        $this->assertDatabaseCount(
            'payments',
            1
        );

        $user->refresh();

        $this->assertSame(
            'PREMIUM',
            $user->account_type
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' =>
                    $user->getKey(),

                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                'status' =>
                    UserEntitlement::STATUS_ACTIVE,
            ]
        );

        Http::assertSentCount(
            4
        );
    }

    public function test_forced_paypal_reversal_creates_adjustment_reverses_affiliate_and_revokes_exact_tied_entitlement(): void
    {
        $this->seed(
            AffiliateStageSeeder::class
        );

        $this->configureWebhook();

        $affiliate =
            $this->createApiUser();

        $buyer =
            $this->createApiUser([
                'account_type' =>
                    'PREMIUM',

                'education_premium_entitlement' =>
                    false,

                'referred_by_user_id' =>
                    $affiliate->getKey(),

                'premium_activated_at' =>
                    now(),
            ]);

        $captureId =
            'PAYPAL-CAPTURE-REVERSE-API12';

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $buyer->getKey(),

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
                        'PAYPAL-ORDER-REVERSE-API12',

                    'cleared_at' =>
                        now(),
                ]);

        $payment =
            Payment::query()
                ->create([
                    'payment_order_id' =>
                        $order->getKey(),

                    'user_id' =>
                        $buyer->getKey(),

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
                        Payment::AFFILIATE_PROCESSED,

                    'affiliate_processed_at' =>
                        now(),

                    'cleared_at' =>
                        now(),
                ]);

        UserEntitlement::query()
            ->create([
                'user_id' =>
                    $buyer->getKey(),

                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                'status' =>
                    UserEntitlement::STATUS_ACTIVE,

                'activated_at' =>
                    now(),

                'payment_id' =>
                    $payment->getKey(),
            ]);

        app(
            AffiliateCommercialService::class
        )->recordClearedPayPalSale(
            $affiliate,
            $buyer,
            $captureId,
            7600
        );

        Http::fakeSequence()
            ->push([
                'access_token' =>
                    'paypal-token',
            ], 200)
            ->push([
                'verification_status' =>
                    'SUCCESS',
            ], 200);

        $this
            ->withHeaders(
                $this->webhookHeaders()
            )
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                [
                    'id' =>
                        'WH-EVENT-REVERSED-API12',

                    'event_type' =>
                        'PAYMENT.CAPTURE.REVERSED',

                    'resource' => [
                        'id' =>
                            $captureId,
                    ],
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                PaymentWebhookEvent::STATUS_PROCESSED
            );

        $payment->refresh();

        $this->assertSame(
            Payment::STATUS_PAYMENT_REVERSED,
            $payment->status
        );

        $this->assertSame(
            Payment::REVERSAL_PAYPAL_PROCESSOR,
            $payment->payment_reversal_code
        );

        $this->assertDatabaseHas(
            'payment_adjustments',
            [
                'payment_id' =>
                    $payment->getKey(),

                'type' =>
                    PaymentAdjustment::TYPE_PAYMENT_REVERSAL,

                'amount_minor' =>
                    -7900,
            ]
        );

        $sale =
            AffiliateQualifiedSale::query()
                ->where(
                    'source_reference',
                    $captureId
                )
                ->firstOrFail();

        $this->assertSame(
            AffiliateQualifiedSale::STATUS_PAYMENT_REVERSED,
            $sale->status
        );

        $this->assertDatabaseHas(
            'user_entitlements',
            [
                'user_id' =>
                    $buyer->getKey(),

                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,

                'status' =>
                    UserEntitlement::STATUS_REVOKED,
            ]
        );

        $buyer->refresh();

        $this->assertSame(
            'FREE',
            $buyer->account_type
        );

        $this->assertFalse(
            $buyer->canAccessPremium()
        );
    }

    public function test_invalid_webhook_signature_cannot_change_payment_state(): void
    {
        $this->configureWebhook();

        Http::fakeSequence()
            ->push([
                'access_token' =>
                    'paypal-token',
            ], 200)
            ->push([
                'verification_status' =>
                    'FAILURE',
            ], 200);

        $this
            ->withHeaders(
                $this->webhookHeaders()
            )
            ->postJson(
                '/api/v1/payments/paypal/webhook',
                [
                    'id' =>
                        'WH-INVALID-API12',

                    'event_type' =>
                        'PAYMENT.CAPTURE.COMPLETED',

                    'resource' => [
                        'id' =>
                            'CAPTURE-INVALID',
                    ],
                ]
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'PAYPAL_WEBHOOK_SIGNATURE_INVALID'
            );

        $this->assertDatabaseCount(
            'payment_webhook_events',
            0
        );
    }

    private function configureWebhook(): void
    {
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
