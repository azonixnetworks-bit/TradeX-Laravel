<?php

namespace Tests\Feature\Screener;

use Tests\TestCase;

class CTraderScreenerContractTest extends TestCase
{
    public function test_ctrader_forex_adapter_is_secret_safe_and_wired_as_primary_forex_provider(): void
    {
        $config = file_get_contents(base_path(
            'config/tradex_screener_ctrader.php'
        ));
        $contract = file_get_contents(base_path(
            'app/Contracts/Screener/CTraderJsonTransport.php'
        ));
        $transport = file_get_contents(base_path(
            'app/Services/Screener/Transports/NativeCTraderJsonWebSocketTransport.php'
        ));
        $provider = file_get_contents(base_path(
            'app/Services/Screener/Providers/CTraderForexProvider.php'
        ));
        $serviceProvider = file_get_contents(base_path(
            'app/Providers/ScreenerServiceProvider.php'
        ));

        foreach (
            [$config, $contract, $transport, $provider, $serviceProvider]
            as $file
        ) {
            $this->assertIsString($file);
        }

        $this->assertStringContainsString(
            'wss://demo.ctraderapi.com:5036/',
            $config
        );
        $this->assertStringContainsString(
            'wss://live.ctraderapi.com:5036/',
            $config
        );
        $this->assertStringContainsString(
            "'DEMO'",
            $config
        );
        $this->assertStringContainsString(
            'CTraderJsonTransport::class',
            $serviceProvider
        );
        $this->assertStringContainsString(
            'CTraderForexProvider::class',
            $serviceProvider
        );
        $this->assertStringContainsString(
            'NativeCTraderJsonWebSocketTransport::class',
            $serviceProvider
        );
        $this->assertStringContainsString(
            "'payloadType' => 2100",
            $provider
        );
        $this->assertStringContainsString(
            "'payloadType' => 2102",
            $provider
        );
        $this->assertStringContainsString(
            "'payloadType' => 2114",
            $provider
        );
        $this->assertStringContainsString(
            "'payloadType' => 2137",
            $provider
        );
        $this->assertStringContainsString(
            "'expect_payload_type' => 2138",
            $provider
        );
        $this->assertStringContainsString(
            '$port !== 5036',
            $transport
        );
        $this->assertStringContainsString(
            "\$scheme !== 'wss'",
            $transport
        );
        $this->assertStringContainsString(
            "'verify_peer' => true",
            $transport
        );
        $this->assertStringContainsString(
            "'verify_peer_name' => true",
            $transport
        );
        $this->assertStringContainsString(
            'fallback_to_twelve_data',
            $provider
        );

        $healthStart = strpos($provider, 'public function health(): array');
        $healthEnd = strpos($provider, 'private function primaryConfigured');
        $this->assertNotFalse($healthStart);
        $this->assertNotFalse($healthEnd);
        $healthBlock = substr(
            $provider,
            (int) $healthStart,
            (int) $healthEnd - (int) $healthStart
        );

        $this->assertStringNotContainsString(
            'client_secret',
            strtolower($healthBlock)
        );
        $this->assertStringNotContainsString(
            'access_token',
            strtolower($healthBlock)
        );
    }
}
