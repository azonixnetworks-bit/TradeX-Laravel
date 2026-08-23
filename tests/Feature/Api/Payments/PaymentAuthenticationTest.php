<?php

namespace Tests\Feature\Api\Payments;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_payment_routes_require_authentication(): void
    {
        $this
            ->getJson(
                '/api/v1/payments/methods'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/v1/payments/activation-status'
            )
            ->assertUnauthorized();

        $this
            ->getJson(
                '/api/v1/payments/history'
            )
            ->assertUnauthorized();

        $this
            ->postJson(
                '/api/v1/payments/paypal/orders'
            )
            ->assertUnauthorized();
    }
}
