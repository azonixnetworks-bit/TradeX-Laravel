<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\RiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use Tests\TestCase;

class IcMarketsCommodityCatalogTest extends TestCase
{
    public function test_ic_markets_commodity_catalog_contains_twenty_four_stable_instruments(): void
    {
        $commodity = app(TradingInstrumentCatalog::class)->markets()['commodity'];
        $instruments = $commodity['instruments'];

        $this->assertSame('IC Markets', $commodity['broker']);
        $this->assertSame('PARTIALLY VERIFIED', $commodity['spec_status']);
        $this->assertSame(24, $commodity['instrument_count']);
        $this->assertCount(24, $instruments);

        foreach ([
            'XAUUSD', 'XAUEUR', 'XAUJPY', 'XAGUSD', 'XAGEUR',
            'XPTUSD', 'XPDUSD', 'XTIUSD', 'XBRUSD', 'XNGUSD',
            'WHEAT', 'CORN', 'SOYBEAN', 'COFFEE', 'SUGAR',
            'COTTON', 'OJ', 'COCOA', 'BRENT', 'WTI',
        ] as $symbol) {
            $this->assertArrayHasKey($symbol, $instruments);
        }
    }

    public function test_commodity_categories_and_representative_contract_sizes_are_preserved(): void
    {
        $instruments = app(TradingInstrumentCatalog::class)
            ->markets()['commodity']['instruments'];

        $counts = [];
        foreach ($instruments as $instrument) {
            $category = $instrument['category'];
            $counts[$category] = ($counts[$category] ?? 0) + 1;
        }

        $this->assertSame(11, $counts['Precious Metals'] ?? 0);
        $this->assertSame(3, $counts['Spot Energy'] ?? 0);
        $this->assertSame(8, $counts['Agriculture'] ?? 0);
        $this->assertSame(2, $counts['Energy Futures'] ?? 0);

        $this->assertSame(100.0, $instruments['XAUUSD']['contract_size']);
        $this->assertSame(1000.0, $instruments['XAGUSD']['contract_size']);
        $this->assertSame(10000.0, $instruments['XNGUSD']['contract_size']);
        $this->assertSame(4.0, $instruments['WHEAT']['contract_size']);
        $this->assertSame(100.0, $instruments['BRENT']['contract_size']);
    }

    public function test_cross_currency_gold_can_use_manual_quote_to_usd_conversion(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'commodity',
            'symbol' => 'XAUEUR',
            'side' => 'BUY',
            'entry' => 2200,
            'stop_loss' => 2190,
            'take_profit' => 2220,
            'risk_amount' => 100,
            'quote_to_usd_rate' => 1.10,
        ]);

        $this->assertEqualsWithDelta(0.09, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(99.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(198.00, $result['potential_profit'], 0.01);
        $this->assertSame('MANUAL', $result['quote_to_usd_conversion']['source']);
        $this->assertSame('IC Markets', $result['specification']['broker']);
        $this->assertSame('Precious Metals', $result['specification']['category']);
    }
}
