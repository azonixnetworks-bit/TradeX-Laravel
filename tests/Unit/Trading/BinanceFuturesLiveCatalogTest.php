<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\BinanceFuturesExchangeInfoService;
use App\Services\Trading\TradingInstrumentCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BinanceFuturesLiveCatalogTest extends TestCase
{
    private const CACHE_KEY = 'tradex:binance-futures:exchange-info:v1';
    private const STALE_CACHE_KEY = 'tradex:binance-futures:exchange-info:stale:v1';

    protected function tearDown(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::STALE_CACHE_KEY);
        parent::tearDown();
    }

    public function test_exchange_info_normalisation_keeps_only_trading_usdt_perpetuals_and_uses_filters(): void
    {
        $service = app(BinanceFuturesExchangeInfoService::class);

        $instruments = $service->normaliseExchangeInfo([
            'symbols' => [
                $this->symbol('BTCUSDT', 'BTC', 'USDT', 'PERPETUAL', 'TRADING'),
                $this->symbol('ETHUSDT', 'ETH', 'USDT', 'PERPETUAL', 'PENDING_TRADING'),
                $this->symbol('BTCUSDC', 'BTC', 'USDC', 'PERPETUAL', 'TRADING'),
                $this->symbol('BTCUSDT_261225', 'BTC', 'USDT', 'CURRENT_QUARTER', 'TRADING'),
            ],
        ]);

        $this->assertSame(['BTCUSDT'], array_keys($instruments));
        $btc = $instruments['BTCUSDT'];

        $this->assertSame('BTC', $btc['base_currency']);
        $this->assertSame('USDT', $btc['quote_currency']);
        $this->assertSame('USDⓈ-M Perpetual', $btc['category']);
        $this->assertSame('TRADING', $btc['provider_status']);
        $this->assertSame('PERPETUAL', $btc['contract_type']);
        $this->assertEqualsWithDelta(0.10, $btc['tick_size'], 0.000000001);
        $this->assertEqualsWithDelta(0.001, $btc['min_lot'], 0.000000001);
        $this->assertEqualsWithDelta(1000.0, $btc['max_lot'], 0.000000001);
        $this->assertEqualsWithDelta(0.001, $btc['lot_step'], 0.000000001);
        $this->assertEqualsWithDelta(0.001, $btc['market_min_lot'], 0.000000001);
        $this->assertEqualsWithDelta(500.0, $btc['market_max_lot'], 0.000000001);
        $this->assertEqualsWithDelta(0.001, $btc['market_lot_step'], 0.000000001);
        $this->assertEqualsWithDelta(5.0, $btc['min_notional'], 0.000000001);
        $this->assertSame('LIVE FILTERS / PARTIALLY VERIFIED', $btc['spec_status']);
    }

    public function test_normal_catalog_uses_deterministic_fallback_during_tests_without_http(): void
    {
        Http::fake();

        $market = app(TradingInstrumentCatalog::class)->markets()['binance_futures'];

        $this->assertSame('TRADEX_FALLBACK_TEST', $market['catalog_source']);
        $this->assertFalse($market['live_catalog_active']);
        $this->assertSame(10, $market['instrument_count']);
        $this->assertArrayHasKey('BTCUSDT', $market['instruments']);
        Http::assertNothingSent();
    }

    public function test_live_exchange_info_can_replace_fallback_and_is_cached(): void
    {
        config()->set('binance_futures.live_catalog.allow_testing', true);
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::STALE_CACHE_KEY);

        Http::fake([
            'https://fapi.binance.com/fapi/v1/exchangeInfo' => Http::response([
                'serverTime' => 1770000000000,
                'symbols' => [
                    $this->symbol('BTCUSDT', 'BTC', 'USDT', 'PERPETUAL', 'TRADING'),
                    $this->symbol('NEWCOINUSDT', 'NEWCOIN', 'USDT', 'PERPETUAL', 'TRADING'),
                ],
            ], 200),
        ]);

        $service = app(BinanceFuturesExchangeInfoService::class);
        $market = $service->hydrateMarket((array) config('binance_futures'));

        $this->assertSame('BINANCE_LIVE', $market['catalog_source']);
        $this->assertTrue($market['live_catalog_active']);
        $this->assertSame(2, $market['instrument_count']);
        $this->assertArrayHasKey('NEWCOINUSDT', $market['instruments']);
        $this->assertSame(1770000000000, $market['catalog_server_time']);

        $cached = $service->hydrateMarket((array) config('binance_futures'));
        $this->assertSame('BINANCE_CACHE', $cached['catalog_source']);
        $this->assertSame(2, $cached['instrument_count']);

        Http::assertSentCount(1);
    }

    /** @return array<string, mixed> */
    private function symbol(
        string $symbol,
        string $base,
        string $quote,
        string $contractType,
        string $status
    ): array {
        return [
            'symbol' => $symbol,
            'pair' => $symbol,
            'contractType' => $contractType,
            'status' => $status,
            'baseAsset' => $base,
            'quoteAsset' => $quote,
            'marginAsset' => $quote,
            'onboardDate' => 1700000000000,
            // Deliberately unrelated precision values: TradeX must use filter values below.
            'pricePrecision' => 8,
            'quantityPrecision' => 8,
            'filters' => [
                [
                    'filterType' => 'PRICE_FILTER',
                    'minPrice' => '0.10',
                    'maxPrice' => '10000000',
                    'tickSize' => '0.10',
                ],
                [
                    'filterType' => 'LOT_SIZE',
                    'minQty' => '0.001',
                    'maxQty' => '1000',
                    'stepSize' => '0.001',
                ],
                [
                    'filterType' => 'MARKET_LOT_SIZE',
                    'minQty' => '0.001',
                    'maxQty' => '500',
                    'stepSize' => '0.001',
                ],
                [
                    'filterType' => 'MIN_NOTIONAL',
                    'notional' => '5',
                ],
            ],
        ];
    }
}
