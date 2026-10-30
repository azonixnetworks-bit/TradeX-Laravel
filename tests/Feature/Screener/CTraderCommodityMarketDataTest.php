<?php

namespace Tests\Feature\Screener;

use App\Services\Screener\CTraderCommodityCatalogService;
use App\Services\Screener\Providers\CTraderCommodityMarketDataProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CTraderCommodityMarketDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('tradex:screener:ctrader-commodity-catalog:v1');
        config()->set('tradex_screener.enabled', true);
        config()->set(
            'tradex_screener.providers.commodity_ctrader.enabled',
            true
        );
        config()->set(
            'tradex_screener.providers.commodity_ctrader.base_url',
            'https://market-data.test'
        );
        config()->set(
            'tradex_screener.providers.commodity_ctrader.output_size',
            3
        );
    }

    public function test_catalog_uses_python_commodity_endpoint(): void
    {
        Http::fake([
            'https://market-data.test/api/commodities/prices' => Http::response([
                'market' => 'commodity',
                'count' => 1,
                'data' => [
                    [
                        'symbol' => 'XAUUSD',
                        'category' => 'XAU',
                        'market' => 'commodity',
                    ],
                ],
            ]),
        ]);

        $catalog = app(CTraderCommodityCatalogService::class)->catalog();

        $this->assertSame('CTRADER_COMMODITY', $catalog['provider']);
        $this->assertSame('XAUUSD', $catalog['symbols'][0]['symbol']);
        $this->assertSame('XAU', $catalog['symbols'][0]['category']);
    }

    public function test_provider_normalises_python_candles(): void
    {
        Http::fake([
            'https://market-data.test/api/commodities/candles/XAUUSD*' => Http::response([
                'market' => 'commodity',
                'symbol' => 'XAUUSD',
                'timeframe' => 'H1',
                'data' => [
                    [
                        'datetime_utc' => '2026-10-30T09:00:00+00:00',
                        'open' => 2700.10,
                        'high' => 2705.20,
                        'low' => 2698.30,
                        'close' => 2702.40,
                        'volume' => 12,
                    ],
                    [
                        'datetime_utc' => '2026-10-30T10:00:00+00:00',
                        'open' => 2702.40,
                        'high' => 2708.00,
                        'low' => 2701.10,
                        'close' => 2706.80,
                        'volume' => 15,
                    ],
                ],
            ]),
        ]);

        $series = app(CTraderCommodityMarketDataProvider::class)->timeSeries(
            ['XAUUSD'],
            '1h',
            3
        );

        $this->assertCount(2, $series['XAUUSD']);
        $this->assertSame(2706.8, $series['XAUUSD'][1]['close']);
    }

    public function test_admin_screener_exposes_commodity_market(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/Admin/Screener/ScreenerMonitorController.php')
        );
        $view = file_get_contents(
            resource_path('views/admin/screener/markets.blade.php')
        );

        $this->assertStringContainsString(
            "['DERIV', 'COMMODITY']",
            $controller
        );
        $this->assertStringContainsString(
            "'COMMODITY' => 'Search commodity instruments'",
            $view
        );
    }
}
