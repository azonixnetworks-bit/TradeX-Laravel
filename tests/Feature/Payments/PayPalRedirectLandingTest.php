<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

class PayPalRedirectLandingTest extends TestCase
{
    public function test_paypal_return_page_does_not_claim_settlement(): void
    {
        $this->get('/payments/paypal/return?token=ORDER-SECRET&PayerID=PAYER-SECRET')
            ->assertOk()
            ->assertSee('PayPal approval received')
            ->assertSee('does not confirm settlement')
            ->assertDontSee('ORDER-SECRET')
            ->assertDontSee('PAYER-SECRET');
    }

    public function test_paypal_cancel_page_is_available(): void
    {
        $this->get('/payments/paypal/cancel')
            ->assertOk()
            ->assertSee('Payment cancelled')
            ->assertSee('No payment was completed');
    }
}
