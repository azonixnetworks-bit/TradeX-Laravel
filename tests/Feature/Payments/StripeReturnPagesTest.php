<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

class StripeReturnPagesTest extends TestCase
{
    public function test_stripe_success_return_page_is_public_and_does_not_claim_settlement(): void
    {
        $response = $this->get('/payments/stripe/success');

        $response->assertOk();
        $response->assertSee('Payment submitted');
        $response->assertSee('does not confirm settlement or premium access');
        $response->assertSee('Stripe-signed webhook');
    }

    public function test_stripe_cancel_return_page_is_public_and_does_not_grant_access(): void
    {
        $response = $this->get('/payments/stripe/cancel');

        $response->assertOk();
        $response->assertSee('Payment cancelled');
        $response->assertSee('No premium access is granted from this browser page');
        $response->assertSee('verified provider callbacks');
    }
}
