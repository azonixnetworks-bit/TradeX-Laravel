<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class ScreenerContractTest extends TestCase
{
    public function test_screener_phase_one_is_provider_independent_and_mobile_hardened(): void
    {
        $providers = file_get_contents(
            base_path(
                'bootstrap/providers.php'
            )
        );

        $routes = file_get_contents(
            base_path(
                'routes/api_screener.php'
            )
        );

        $config = file_get_contents(
            base_path(
                'config/tradex_screener.php'
            )
        );

        $provider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/TwelveDataForexProvider.php'
            )
        );

        $this->assertStringContainsString(
            'ScreenerServiceProvider::class',
            $providers
        );

        $this->assertStringContainsString(
            "'auth:sanctum'",
            $routes
        );

        $this->assertStringContainsString(
            "'tradex.mobile'",
            $routes
        );

        $this->assertStringContainsString(
            "'CRYPTO'",
            $config
        );

        $this->assertStringContainsString(
            "'DERIV'",
            $config
        );

        $this->assertStringContainsString(
            "'STOCKS'",
            $config
        );

        $this->assertStringContainsString(
            "'provider_interval' => '4h'",
            $config
        );

        $this->assertStringContainsString(
            "'timezone' => 'UTC'",
            $provider
        );

        $this->assertStringNotContainsString(
            'tradingview.com/screener',
            strtolower($provider)
        );
    }

    public function test_screener_api_key_is_environment_only(): void
    {
        $config = file_get_contents(
            base_path(
                'config/tradex_screener.php'
            )
        );

        $env = file_get_contents(
            base_path(
                '.env.example'
            )
        );

        $this->assertStringContainsString(
            "env(\n                'TRADEX_TWELVE_DATA_API_KEY'",
            $config
        );

        $this->assertStringContainsString(
            'TRADEX_TWELVE_DATA_API_KEY=',
            $env
        );

        $this->assertStringNotContainsString(
            'test-twelve-data-key',
            $config
        );
    }
}
