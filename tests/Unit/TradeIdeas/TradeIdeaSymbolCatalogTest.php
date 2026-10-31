<?php

namespace Tests\Unit\TradeIdeas;

use App\Services\TradeIdeas\TradeIdeaSymbolCatalog;
use Tests\TestCase;

class TradeIdeaSymbolCatalogTest extends TestCase
{
    public function test_catalog_contains_forex_crypto_deriv_and_stock_suggestions(): void
    {
        $catalog = app(TradeIdeaSymbolCatalog::class)->grouped();

        $this->assertSame(['FOREX', 'CRYPTO', 'DERIV', 'STOCKS'], array_keys($catalog));
        $this->assertContains('EURUSD', array_column($catalog['FOREX'], 'symbol'));
        $this->assertContains('XAUUSD', array_column($catalog['FOREX'], 'symbol'));
        $this->assertContains('BTCUSD', array_column($catalog['CRYPTO'], 'symbol'));
        $this->assertContains('BTCUSDT', array_column($catalog['CRYPTO'], 'symbol'));
        $this->assertContains('BOOM1000', array_column($catalog['DERIV'], 'symbol'));
        $this->assertContains('R_75', array_column($catalog['DERIV'], 'symbol'));
        $this->assertContains('AAPL', array_column($catalog['STOCKS'], 'symbol'));
        $this->assertContains('RELIANCE', array_column($catalog['STOCKS'], 'symbol'));
    }

    public function test_catalog_symbols_are_unique_within_each_category(): void
    {
        foreach (app(TradeIdeaSymbolCatalog::class)->grouped() as $items) {
            $symbols = array_column($items, 'symbol');
            $this->assertSame($symbols, array_values(array_unique($symbols)));
        }
    }
}
