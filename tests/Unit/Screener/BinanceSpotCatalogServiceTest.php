<?php

namespace Tests\Unit\Screener;

use App\Services\Screener\BinanceSpotCatalogService;
use Tests\TestCase;

class BinanceSpotCatalogServiceTest extends TestCase
{
    public function test_it_normalises_only_active_usdt_spot_symbols_with_logos(): void
    {
        $symbols = app(BinanceSpotCatalogService::class)->normalise([
            'symbols' => [
                [
                    'symbol' => 'BTCUSDT',
                    'baseAsset' => 'BTC',
                    'quoteAsset' => 'USDT',
                    'status' => 'TRADING',
                    'isSpotTradingAllowed' => true,
                ],
                [
                    'symbol' => 'ETHBTC',
                    'baseAsset' => 'ETH',
                    'quoteAsset' => 'BTC',
                    'status' => 'TRADING',
                    'isSpotTradingAllowed' => true,
                ],
                [
                    'symbol' => 'OLDUSDT',
                    'baseAsset' => 'OLD',
                    'quoteAsset' => 'USDT',
                    'status' => 'BREAK',
                    'isSpotTradingAllowed' => true,
                ],
            ],
        ]);

        $this->assertCount(1, $symbols);
        $this->assertSame('BTCUSDT', $symbols[0]['symbol']);
        $this->assertSame('BTC', $symbols[0]['base_currency']);
        $this->assertStringContainsString('/btc.png', $symbols[0]['logo_url']);
    }
}
