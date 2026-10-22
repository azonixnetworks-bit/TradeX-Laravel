<?php

namespace Tests\Unit\Screener;

use App\Services\Screener\MarketBubbleService;
use Tests\TestCase;

class MarketBubbleServiceTest extends TestCase
{
    public function test_it_normalises_mini_tickers_and_sorts_by_quote_volume(): void
    {
        $service = new MarketBubbleService();

        $dataset = $service->normalise([
            [
                'symbol' => 'BTCUSDT',
                'lastPrice' => '60000',
                'volume' => '100',
                'quoteVolume' => '6000000',
                'openPrice' => '57000',
                'highPrice' => '61000',
                'lowPrice' => '56000',
                'weightedAvgPrice' => '59000',
                'count' => 1200,
            ],
            [
                'symbol' => 'ETHUSDT',
                'lastPrice' => '3000',
                'volume' => '500',
                'quoteVolume' => '1500000',
                'openPrice' => '3070',
                'highPrice' => '3100',
                'lowPrice' => '2950',
                'weightedAvgPrice' => '3020',
                'count' => 800,
            ],
            [
                'symbol' => 'UNKNOWNBTC',
                'lastPrice' => '1',
                'priceChangePercent' => '50',
                'volume' => '999999',
                'quoteVolume' => '999999999',
            ],
        ], 100);

        $this->assertSame('CRYPTO', $dataset['market']);
        $this->assertSame('24H', $dataset['timeframe']);
        $this->assertSame('BINANCE', $dataset['provider']);
        $this->assertCount(2, $dataset['assets']);
        $this->assertSame('BTCUSDT', $dataset['assets'][0]['symbol']);
        $this->assertSame('ETHUSDT', $dataset['assets'][1]['symbol']);
        $this->assertSame('BTC / USDT', $dataset['assets'][0]['name']);
        $this->assertSame(5.26, round($dataset['assets'][0]['change_percent'], 2));
        $this->assertSame(-2.28, round($dataset['assets'][1]['change_percent'], 2));
        $this->assertSame(2, $dataset['summary']['total_assets']);
        $this->assertSame(1, $dataset['summary']['gainers']);
        $this->assertSame(1, $dataset['summary']['losers']);
        $this->assertSame(0, $dataset['summary']['unchanged']);
        $this->assertSame(1.49, $dataset['summary']['average_change']);
    }
}
