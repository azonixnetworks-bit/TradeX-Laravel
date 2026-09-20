<?php

namespace Tests\Feature\Payments;

use App\Services\Payments\NowPaymentsConfigurationSafety;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class NowPaymentsConfigurationSafetyTest extends TestCase
{
    public function test_live_configuration_requires_the_official_api_endpoint_and_https_urls(): void
    {
        $this->configureLive();
        config()->set('tradex_integrations.nowpayments.live_base_url', 'https://merchant-proxy.example');

        $checks = app(NowPaymentsConfigurationSafety::class)->checks();

        $this->assertFalse($checks['official_api_endpoint']);
        $this->assertFalse(app(NowPaymentsConfigurationSafety::class)->configured());
    }

    public function test_safe_live_configuration_passes_all_checks(): void
    {
        $this->configureLive();

        $checks = app(NowPaymentsConfigurationSafety::class)->checks();

        $this->assertNotContains(false, $checks, true);
        $this->assertTrue(app(NowPaymentsConfigurationSafety::class)->configured());
    }

    public function test_readiness_command_can_probe_status_without_creating_a_payment(): void
    {
        $this->configureLive();
        Http::fake([
            'https://api.nowpayments.io/v1/status' => Http::response([
                'message' => 'OK',
            ]),
        ]);

        $this->artisan('tradex:nowpayments-check --probe')
            ->expectsOutputToContain('API status probe passed')
            ->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'GET'
            && $request->url() === 'https://api.nowpayments.io/v1/status'
            && $request->hasHeader('x-api-key', 'live-api-key')
        );
    }

    public function test_live_invoice_redirect_must_remain_on_nowpayments_domain(): void
    {
        $this->configureLive();
        $configuration = app(NowPaymentsConfigurationSafety::class);

        $this->assertTrue($configuration->invoiceUrlIsAllowed(
            'https://nowpayments.io/payment/?iid=invoice-123'
        ));
        $this->assertFalse($configuration->invoiceUrlIsAllowed(
            'https://payments.example/invoice-123'
        ));
    }

    private function configureLive(): void
    {
        config()->set('app.url', 'https://tradex.10x.cloudvideo.online');
        URL::forceRootUrl('https://tradex.10x.cloudvideo.online');
        URL::forceScheme('https');
        config()->set('tradex_integrations.nowpayments.environment', 'LIVE');
        config()->set('tradex_integrations.nowpayments.api_key', 'live-api-key');
        config()->set('tradex_integrations.nowpayments.ipn_secret', 'live-ipn-secret');
        config()->set('tradex_integrations.nowpayments.live_base_url', 'https://api.nowpayments.io');
        config()->set('tradex_integrations.nowpayments.success_url', 'https://tradex.10x.cloudvideo.online/payments/success');
        config()->set('tradex_integrations.nowpayments.cancel_url', 'https://tradex.10x.cloudvideo.online/payments/cancel');
    }
}
