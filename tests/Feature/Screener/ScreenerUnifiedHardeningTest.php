<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class ScreenerUnifiedHardeningTest extends TestCase
{
    public function test_phase_five_adds_secret_safe_health_without_provider_network_probes(): void
    {
        $routes = file_get_contents(base_path('routes/api_screener.php'));
        $controller = file_get_contents(base_path(
            'app/Http/Controllers/Api/Screener/ScreenerController.php'
        ));
        $health = file_get_contents(base_path(
            'app/Services/Screener/ScreenerHealthService.php'
        ));

        $this->assertStringContainsString(
            "'/health'",
            $routes
        );
        $this->assertStringContainsString(
            "'SCREENER-05'",
            $controller
        );
        $this->assertStringContainsString(
            'ScreenerHealthService',
            $controller
        );
        $this->assertStringContainsString(
            'fresh_datasets_cached',
            $health
        );
        $this->assertStringContainsString(
            'fallback_datasets_cached',
            $health
        );
        $this->assertStringContainsString(
            'safeHost',
            $health
        );

        $this->assertStringNotContainsString(
            'Http::',
            $health
        );
        $this->assertStringNotContainsString(
            'api_key',
            strtolower($health)
        );
        $this->assertStringNotContainsString(
            'access_token',
            strtolower($health)
        );
    }

    public function test_provider_specific_interval_and_output_size_routing_is_explicit(): void
    {
        $engine = file_get_contents(base_path(
            'app/Services/Screener/ScreenerEngine.php'
        ));

        $this->assertStringContainsString(
            "'BINANCE' =>",
            $engine
        );
        $this->assertStringContainsString(
            "'DERIV' =>",
            $engine
        );
        $this->assertStringContainsString(
            "['deriv_granularity']",
            $engine
        );
        $this->assertStringContainsString(
            "provider->name() === 'TWELVE_DATA_STOCKS'",
            $engine
        );
        $this->assertStringContainsString(
            'tradex_screener_stocks.provider.output_size',
            $engine
        );
        $this->assertStringContainsString(
            'tradex_screener.providers.deriv.output_size',
            $engine
        );
    }
}
