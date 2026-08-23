<?php

namespace Tests\Feature\Api\Payments;

use App\Models\PaymentOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PaymentIdorTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_user_cannot_read_or_capture_another_users_payment_order(): void
    {
        $owner =
            $this->createApiUser();

        $other =
            $this->createApiUser();

        $order =
            PaymentOrder::query()
                ->create([
                    'user_id' =>
                        $owner->getKey(),

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
                        'PAYPAL-ORDER-IDOR',
                ]);

        $token =
            $this->issueBoundToken(
                $other
            );

        $this
            ->withToken($token)
            ->getJson(
                '/api/v1/payments/orders/'
                .$order->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ORDER_NOT_AVAILABLE'
            );

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/payments/paypal/orders/'
                .$order->uuid
                .'/capture'
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'PAYMENT_ORDER_NOT_AVAILABLE'
            );
    }
}
