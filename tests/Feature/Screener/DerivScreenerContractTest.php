<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class DerivScreenerContractTest extends TestCase
{
    public function test_deriv_uses_public_websocket_market_data_without_account_credentials(): void
    {
        $provider = file_get_contents(
            base_path(
                'app/Services/Screener/Providers/DerivMarketDataProvider.php'
            )
        );
        $transport = file_get_contents(
            base_path(
                'app/Services/Screener/Transports/NativeDerivWebSocketTransport.php'
            )
        );
        $config = file_get_contents(
            base_path('config/tradex_screener.php')
        );
        $env = file_get_contents(
            base_path('.env.example')
        );
        $serviceProvider = file_get_contents(
            base_path(
                'app/Providers/ScreenerServiceProvider.php'
            )
        );

        $this->assertStringContainsString(
            "return 'DERIV';",
            $provider
        );
        $this->assertStringContainsString(
            "'ticks_history' => \$symbol",
            $provider
        );
        $this->assertStringContainsString(
            "'style' => 'candles'",
            $provider
        );
        $this->assertStringContainsString(
            "'subscribe' => 0",
            $provider
        );
        $this->assertStringContainsString(
            "'deriv_granularity' => 3600",
            $config
        );
        $this->assertStringContainsString(
            "'api.derivws.com'",
            $config
        );
        $this->assertStringContainsString(
            'wss://api.derivws.com/trading/v1/options/ws/public',
            $env
        );
        $this->assertStringContainsString(
            'verify_peer',
            $transport
        );
        $this->assertStringContainsString(
            'verify_peer_name',
            $transport
        );
        $this->assertStringContainsString(
            'DerivMarketDataProvider::class',
            $serviceProvider
        );
        $this->assertStringContainsString(
            'DerivWebSocketTransport::class',
            $serviceProvider
        );

        $this->assertStringNotContainsString(
            'TRADEX_DERIV_API_TOKEN',
            $env
        );
        $this->assertStringNotContainsString(
            'Authorization:',
            $transport
        );
        $this->assertStringNotContainsString(
            'tradingview.com/screener',
            strtolower($provider.$transport)
        );
    }

    public function test_deriv_endpoint_is_restricted_to_approved_secure_host(): void
    {
        $transport = file_get_contents(
            base_path(
                'app/Services/Screener/Transports/NativeDerivWebSocketTransport.php'
            )
        );
        $config = file_get_contents(
            base_path('config/tradex_screener.php')
        );

        $this->assertStringContainsString(
            "\$scheme !== 'wss'",
            $transport
        );
        $this->assertStringContainsString(
            'in_array($host, $allowedHosts, true)',
            $transport
        );
        $this->assertStringContainsString(
            "'allowed_hosts' => [",
            $config
        );
        $this->assertStringContainsString(
            "'api.derivws.com'",
            $config
        );
    }
}
