<?php

namespace Tests\Feature\Api\Payments;

use App\Services\Payments\StripeCheckoutService;
use App\Services\Payments\StripeConfigurationSafety;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeLiveSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_customer_return_pages_are_public_and_do_not_claim_settlement(): void
    {
        $this->get('/payments/stripe/success')
            ->assertOk()
            ->assertSee('Card payment submitted')
            ->assertSee('does not confirm settlement');

        $this->get('/payments/stripe/cancel')
            ->assertOk()
            ->assertSee('Payment cancelled');
    }

    public function test_live_checkout_rejects_non_stripe_redirect_host(): void
    {
        $service = app(StripeCheckoutService::class);
        $method = new \ReflectionMethod($service, 'checkoutHostIsAllowed');
        config()->set('tradex_integrations.stripe.environment', 'LIVE');

        $this->assertTrue($method->invoke($service, 'https://checkout.stripe.com/c/pay/test'));
        $this->assertFalse($method->invoke($service, 'https://stripe.example.test/c/pay/test'));
    }

    public function test_live_configuration_accepts_a_restricted_secret_key(): void
    {
        config()->set('tradex_integrations.stripe.environment', 'LIVE');
        config()->set('tradex_integrations.stripe.publishable_key', 'pk_live_example');
        config()->set('tradex_integrations.stripe.secret_key', 'rk_live_example');
        config()->set('tradex_integrations.stripe.webhook_secret', 'whsec_example');
        config()->set('tradex_integrations.stripe.base_url', 'https://api.stripe.com');
        config()->set('tradex_integrations.stripe.success_url', 'https://example.com/payments/stripe/success');
        config()->set('tradex_integrations.stripe.cancel_url', 'https://example.com/payments/stripe/cancel');

        $this->assertTrue(app(StripeConfigurationSafety::class)->configured());
    }
}
