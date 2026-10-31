<?php

namespace Tests\Feature\Screener;

use App\Services\Screener\CTraderDerivCatalogService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CTraderDerivCatalogServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('tradex:screener:ctrader-deriv-catalog:v1');
        config()->set(
            'tradex_screener.providers.deriv.base_url',
            'https://market-data.test'
        );
    }

    public function test_catalog_preserves_public_index_identity_and_display_name(): void
    {
        Http::fake([
            'https://market-data.test/api/deriv/prices' => Http::response([
                'market' => 'deriv',
                'count' => 1,
                'data' => [
                    [
                        'symbol' => 'OTC_SPC',
                        'display_name' => 'US 500',
                        'category' => 'International Indices',
                        'asset_class' => 'INDEX',
                        'source' => 'DERIV_PUBLIC',
                    ],
                ],
            ]),
        ]);

        $catalog = app(CTraderDerivCatalogService::class)->catalog();

        $this->assertSame('TRADEX_MARKET_DATA_FASTAPI', $catalog['source']);
        $this->assertSame(1, $catalog['count']);
        $this->assertSame('OTC_SPC', $catalog['symbols'][0]['symbol']);
        $this->assertSame('US 500', $catalog['symbols'][0]['name']);
        $this->assertSame('INDEX', $catalog['symbols'][0]['asset_class']);
        $this->assertSame('DERIV_PUBLIC', $catalog['symbols'][0]['source']);
    }
}
