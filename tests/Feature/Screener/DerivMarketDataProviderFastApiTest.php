<?php

namespace Tests\Feature\Screener;

use App\Services\Screener\Providers\DerivMarketDataProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DerivMarketDataProviderFastApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tradex_screener.enabled', true);
        config()->set('tradex_screener.providers.deriv.enabled', true);
        config()->set(
            'tradex_screener.providers.deriv.base_url',
            'https://market-data.test'
        );
        config()->set('tradex_screener.providers.deriv.output_size', 3);
    }

    public function test_international_index_candles_are_read_from_fast_api(): void
    {
        Http::fake([
            'https://market-data.test/api/deriv/candles/OTC_SPC*' => Http::response([
                'market' => 'deriv',
                'symbol' => 'OTC_SPC',
                'timeframe' => 'H1',
                'count' => 3,
                'data' => $this->candles(),
            ]),
        ]);

        $result = app(DerivMarketDataProvider::class)->timeSeries(
            ['OTC_SPC'],
            '3600',
            3
        );

        $this->assertArrayHasKey('OTC_SPC', $result);
        $this->assertCount(3, $result['OTC_SPC']);
        $this->assertSame(7640.7, $result['OTC_SPC'][2]['close']);

        Http::assertSent(fn ($request): bool => $request->url()
                === 'https://market-data.test/api/deriv/candles/OTC_SPC?timeframe=H1&count=3'
        );
    }

    public function test_international_index_live_quote_keeps_bid_and_ask_side(): void
    {
        Http::fake([
            'https://market-data.test/api/deriv/price/OTC_SPC' => Http::response([
                'symbol' => 'OTC_SPC',
                'bid' => 7639.9,
                'ask' => 7640.3,
                'digits' => 2,
                'updated_at' => '2026-10-30T11:28:09+00:00',
            ]),
        ]);

        $provider = app(DerivMarketDataProvider::class);

        $this->assertSame(7640.3, $provider->livePrice('OTC_SPC', 'BUY')['price']);
        $this->assertSame(7639.9, $provider->livePrice('OTC_SPC', 'SELL')['price']);
    }

    private function candles(): array
    {
        return [
            [
                'datetime_utc' => '2026-10-30T09:00:00+00:00',
                'open' => 7655.2,
                'high' => 7657.8,
                'low' => 7644.3,
                'close' => 7646.6,
                'volume' => null,
            ],
            [
                'datetime_utc' => '2026-10-30T10:00:00+00:00',
                'open' => 7646.5,
                'high' => 7658.6,
                'low' => 7638.3,
                'close' => 7657.3,
                'volume' => null,
            ],
            [
                'datetime_utc' => '2026-10-30T11:00:00+00:00',
                'open' => 7657.2,
                'high' => 7658.1,
                'low' => 7638.6,
                'close' => 7640.7,
                'volume' => null,
            ],
        ];
    }
}
