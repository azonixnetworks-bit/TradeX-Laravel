<?php

namespace Tests\Feature\Payments;

use Tests\TestCase;

class NowPaymentsRedirectLandingTest extends TestCase
{
    public function test_return_page_does_not_claim_payment_is_already_settled(): void
    {
        $this->get('/payments/nowpayments/return?invoice_id=provider-secret')
            ->assertOk()
            ->assertSee('Crypto payment submitted')
            ->assertSee('does not confirm settlement')
            ->assertDontSee('provider-secret');
    }

    public function test_cancel_page_confirms_no_payment_was_completed(): void
    {
        $this->get('/payments/nowpayments/cancel')
            ->assertOk()
            ->assertSee('Payment cancelled')
            ->assertSee('No payment was completed');
    }
}
