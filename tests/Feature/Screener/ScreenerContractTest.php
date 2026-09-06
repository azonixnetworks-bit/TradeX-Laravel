<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class ScreenerContractTest extends TestCase
{
    public function test_screener_phase_two_is_provider_independent_and_mobile_hardened(): void
    {
        $providers = file_get_contents(
            base_path('bootstrap/providers.php')
        );

        $routes = file_get_contents(
            base_path('routes/api_screener.php')
        );

        $config = file_get_contents(
            base_path('config/tradex_screener.php')
        );

        $forexProvider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/TwelveDataForexProvider.php'
            )
        );

        $cryptoProvider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/BinanceCryptoProvider.php'
            )
        );

        $serviceProvider = file_get_contents(
            base_path(
                'app/Providers/ScreenerServiceProvider.php'
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
            "'binance_interval' => '4h'",
            $config
        );

        $this->assertStringContainsString(
            "'provider_interval' => '4h'",
            $config
        );

        $this->assertStringContainsString(
            "'timezone' => 'UTC'",
            $forexProvider
        );

        $this->assertStringContainsString(
            "return 'CRYPTO';",
            $cryptoProvider
        );

        $this->assertStringContainsString(
            "'/api/v3/klines'",
            $cryptoProvider
        );

        $this->assertStringContainsString(
            'tradex.screener.providers',
            $serviceProvider
        );

        $this->assertStringNotContainsString(
            'tradingview.com/screener',
            strtolower($forexProvider.$cryptoProvider)
        );
    }

    public function test_screener_provider_credentials_are_safe_by_contract(): void
    {
        $config = file_get_contents(
            base_path('config/tradex_screener.php')
        );

        $env = file_get_contents(
            base_path('.env.example')
        );

        $cryptoProvider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/BinanceCryptoProvider.php'
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

        $this->assertStringContainsString(
            'TRADEX_BINANCE_MARKET_DATA_BASE_URL=https://data-api.binance.vision',
            $env
        );

        $this->assertStringNotContainsString(
            'TRADEX_BINANCE_API_KEY',
            $env
        );

        $this->assertStringNotContainsString(
            'X-MBX-APIKEY',
            $cryptoProvider
        );

        $this->assertStringNotContainsString(
            'test-twelve-data-key',
            $config
        );
    }
}
