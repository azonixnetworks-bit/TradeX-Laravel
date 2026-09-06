<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class StocksScreenerContractTest extends TestCase
{
    public function test_stocks_are_region_aware_and_cse_is_not_scraped(): void
    {
        $config = file_get_contents(
            base_path('config/tradex_screener_stocks.php')
        );
        $provider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/TwelveDataStocksProvider.php'
            )
        );
        $serviceProvider = file_get_contents(
            base_path('app/Providers/ScreenerServiceProvider.php')
        );

        $this->assertStringContainsString(
            "'SRI_LANKA' => [",
            $config
        );
        $this->assertStringContainsString(
            "'status' => 'PROVIDER_REQUIRED'",
            $config
        );
        $this->assertStringContainsString(
            "'INDIA' => [",
            $config
        );
        $this->assertStringContainsString(
            "'supported_timeframes' => ['D1']",
            $config
        );
        $this->assertStringContainsString(
            "'INTERNATIONAL' => [",
            $config
        );
        $this->assertStringContainsString(
            "'mic_code' => 'XNSE'",
            $config
        );
        $this->assertStringContainsString(
            "'mic_code' => 'XNAS'",
            $config
        );
        $this->assertStringContainsString(
            "'mic_code' => 'XNYS'",
            $config
        );
        $this->assertStringContainsString(
            "return 'STOCKS';",
            $provider
        );
        $this->assertStringContainsString(
            "'/time_series'",
            $provider
        );
        $this->assertStringContainsString(
            "'timezone' => 'UTC'",
            $provider
        );
        $this->assertStringContainsString(
            "\$query['mic_code'] = \$micCode",
            $provider
        );
        $this->assertStringContainsString(
            'TwelveDataStocksProvider::class',
            $serviceProvider
        );
        $this->assertStringContainsString(
            'StocksScreenerEngine',
            $serviceProvider
        );

        $combined = strtolower($config.$provider);

        $this->assertStringNotContainsString(
            'tradingview.com/screener',
            $combined
        );
        $this->assertStringNotContainsString(
            'www.cse.lk/api',
            $combined
        );
    }

    public function test_stock_display_rights_are_fail_closed_by_default(): void
    {
        $config = file_get_contents(
            base_path('config/tradex_screener_stocks.php')
        );
        $env = file_get_contents(
            base_path('.env.example')
        );
        $provider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/TwelveDataStocksProvider.php'
            )
        );

        $this->assertStringContainsString(
            "'external_display_approved' => env(",
            $config
        );
        $this->assertStringContainsString(
            "'TRADEX_STOCKS_EXTERNAL_DISPLAY_APPROVED'",
            $config
        );
        $this->assertStringContainsString(
            'TRADEX_STOCKS_EXTERNAL_DISPLAY_APPROVED=false',
            $env
        );
        $this->assertStringContainsString(
            'tradex_screener_stocks.provider.external_display_approved',
            $provider
        );
        $this->assertStringContainsString(
            "env('TRADEX_TWELVE_DATA_API_KEY', '')",
            $config
        );
        $this->assertStringNotContainsString(
            'test-stocks-key',
            $config.$env
        );
    }
}
