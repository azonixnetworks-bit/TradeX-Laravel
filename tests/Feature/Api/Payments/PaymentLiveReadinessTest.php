<?php

namespace Tests\Feature\Api\Payments;

use App\Services\Payments\PaymentLiveReadinessService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentLiveReadinessTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceRootUrl(null);
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_sandbox_readiness_passes_with_complete_secret_safe_configuration(): void
    {
        $this->configureSandbox();

        $result = app(PaymentLiveReadinessService::class)->evaluate(false);

        $this->assertTrue($result['ready']);
        $this->assertSame(0, $result['failed_critical']);
        $this->assertSame('SANDBOX_OR_LIVE', $result['mode']);

        $names = collect($result['checks'])->pluck('name')->all();
        $this->assertContains('Stripe webhook route', $names);
        $this->assertContains('NOWPayments IPN route', $names);
    }

    public function test_production_readiness_fails_closed_for_sandbox_credentials_and_test_environment(): void
    {
        $this->configureSandbox();

        $result = app(PaymentLiveReadinessService::class)->evaluate(true);

        $this->assertFalse($result['ready']);
        $this->assertGreaterThan(0, $result['failed_critical']);

        $failed = collect($result['checks'])
            ->where('passed', false)
            ->pluck('name')
            ->all();

        $this->assertContains('Stripe environment', $failed);
        $this->assertContains('NOWPayments environment', $failed);
        $this->assertContains('Application environment', $failed);
    }

    public function test_readiness_command_never_prints_payment_credentials(): void
    {
        $this->configureSandbox();

        $exitCode = Artisan::call('tradex:payment-live-check');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Payment integration readiness PASSED', $output);

        foreach ([
            'pk_test_payment_live_01',
            'sk_test_payment_live_01_secret',
            'whsec_payment_live_01_secret',
            'nowpayments-api-key-payment-live-01',
            'nowpayments-ipn-secret-payment-live-01',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_readiness_rejects_local_or_insecure_callback_urls(): void
    {
        $this->configureSandbox();

        config()->set('app.url', 'http://localhost:8000');
        URL::forceRootUrl('http://localhost:8000');
        URL::forceScheme('http');
        config()->set('tradex_integrations.stripe.success_url', 'http://localhost:8000/payments/success');
        config()->set('tradex_integrations.stripe.cancel_url', 'http://localhost:8000/payments/cancel');
        config()->set('tradex_integrations.nowpayments.success_url', 'http://tradex.local/payments/success');
        config()->set('tradex_integrations.nowpayments.cancel_url', 'http://tradex.local/payments/cancel');

        $result = app(PaymentLiveReadinessService::class)->evaluate(false);

        $this->assertFalse($result['ready']);

        $failed = collect($result['checks'])
            ->where('passed', false)
            ->pluck('name')
            ->all();

        $this->assertContains('Stripe success URL', $failed);
        $this->assertContains('Stripe webhook route', $failed);
        $this->assertContains('NOWPayments success URL', $failed);
        $this->assertContains('NOWPayments IPN route', $failed);
    }

    private function configureSandbox(): void
    {
        config()->set('app.url', 'https://staging.tradex.example');
        URL::forceRootUrl('https://staging.tradex.example');
        URL::forceScheme('https');

        config()->set('tradex_integrations.stripe.enabled', true);
        config()->set('tradex_integrations.stripe.environment', 'SANDBOX');
        config()->set('tradex_integrations.stripe.countries', ['LK', 'IN']);
        config()->set('tradex_integrations.stripe.publishable_key', 'pk_test_payment_live_01');
        config()->set('tradex_integrations.stripe.secret_key', 'sk_test_payment_live_01_secret');
        config()->set('tradex_integrations.stripe.webhook_secret', 'whsec_payment_live_01_secret');
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
    }
}
