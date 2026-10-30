<?php

namespace Tests\Unit\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Services\Screener\Providers\CTraderForexMarketDataProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CTraderForexMarketDataProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('tradex_screener.enabled', true);
        config()->set('tradex_screener.providers.forex_ctrader.enabled', true);
        config()->set(
            'tradex_screener.providers.forex_ctrader.base_url',
            'http://market-data.test'
        );
        config()->set('tradex_screener.providers.forex_ctrader.output_size', 260);
    }

    public function test_it_normalises_candles_and_preserves_requested_symbol_key(): void
    {
        Http::fake([
            'http://market-data.test/api/forex/candles/EURUSD*' => Http::response([
                'market' => 'forex',
                'symbol' => 'EURUSD',
                'timeframe' => 'H1',
                'count' => 2,
                'data' => [
                    [
                        'datetime_utc' => '2026-10-30T09:00:00+00:00',
                        'open' => 1.1600,
                        'high' => 1.1620,
                        'low' => 1.1590,
                        'close' => 1.1610,
                        'volume' => 12,
                    ],
                    [
                        'datetime_utc' => '2026-10-30T10:00:00+00:00',
                        'open' => 1.1610,
                        'high' => 1.1640,
                        'low' => 1.1600,
                        'close' => 1.1630,
                        'volume' => 18,
                    ],
                ],
            ], 200),
        ]);

        $series = app(CTraderForexMarketDataProvider::class)
            ->timeSeries(['EUR/USD'], '1h', 260);

        $this->assertArrayHasKey('EUR/USD', $series);
        $this->assertCount(2, $series['EUR/USD']);
        $this->assertSame(1.163, $series['EUR/USD'][1]['close']);

        Http::assertSent(
            static fn ($request): bool => str_starts_with(
                $request->url(),
                'http://market-data.test/api/forex/candles/EURUSD'
            )
                && $request['timeframe'] === 'H1'
                && (int) $request['count'] === 260
        );
    }

    public function test_it_preserves_http_failure_code_when_all_symbols_fail(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'upstream failure'], 500),
        ]);

        try {
            app(CTraderForexMarketDataProvider::class)
                ->timeSeries(['EUR/USD'], '1h', 260);

            $this->fail('Expected the provider to reject an upstream HTTP failure.');
        } catch (ScreenerProviderException $exception) {
            $this->assertSame(
                'SCREENER_PROVIDER_HTTP_ERROR',
                $exception->providerCode
            );
        }
    }
}
