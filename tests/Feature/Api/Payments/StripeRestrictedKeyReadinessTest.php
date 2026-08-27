<?php

namespace Tests\Feature\Api\Payments;

use App\Services\Payments\PaymentLiveReadinessService;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StripeRestrictedKeyReadinessTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_sandbox_readiness_accepts_a_stripe_restricted_server_key(): void
    {
        config()->set('app.url', 'https://staging.tradex.example');
        URL::forceRootUrl('https://staging.tradex.example');
        URL::forceScheme('https');

        config()->set('tradex_integrations.stripe.enabled', true);
        config()->set('tradex_integrations.stripe.environment', 'SANDBOX');
        config()->set('tradex_integrations.stripe.countries', ['LK', 'IN']);
        config()->set('tradex_integrations.stripe.publishable_key', 'pk_test_payment_live_01');
        config()->set('tradex_integrations.stripe.secret_key', 'rk_test_payment_live_01_restricted');
        config()->set('tradex_integrations.stripe.webhook_secret', 'whsec_payment_live_01');
        config()->set('tradex_integrations.stripe.base_url', 'https://api.stripe.com');
        config()->set('tradex_integrations.stripe.success_url', 'https://staging.tradex.example/payments/success');
        config()->set('tradex_integrations.stripe.cancel_url', 'https://staging.tradex.example/payments/cancel');
        config()->set('tradex_integrations.stripe.webhook_tolerance_seconds', 300);

        config()->set('tradex_integrations.nowpayments.enabled', true);
        config()->set('tradex_integrations.nowpayments.environment', 'SANDBOX');
        config()->set('tradex_integrations.nowpayments.countries', ['LK', 'IN']);
        config()->set('tradex_integrations.nowpayments.api_key', 'nowpayments-api-key-payment-live-01');
        config()->set('tradex_integrations.nowpayments.ipn_secret', 'nowpayments-ipn-secret-payment-live-01');
        config()->set('tradex_integrations.nowpayments.sandbox_base_url', 'https://sandbox.nowpayments.example');
        config()->set('tradex_integrations.nowpayments.success_url', 'https://staging.tradex.example/payments/success');
        config()->set('tradex_integrations.nowpayments.cancel_url', 'https://staging.tradex.example/payments/cancel');

        $result = app(PaymentLiveReadinessService::class)->evaluate(false);

        $stripeServerKey = collect($result['checks'])
            ->firstWhere('name', 'Stripe server API key');

        $this->assertIsArray($stripeServerKey);
        $this->assertTrue($stripeServerKey['passed']);
        $this->assertTrue($result['ready']);
    }
}
