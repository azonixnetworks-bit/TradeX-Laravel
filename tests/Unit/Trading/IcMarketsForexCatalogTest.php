<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\TradingInstrumentCatalog;
use Tests\TestCase;

class IcMarketsForexCatalogTest extends TestCase
{
    public function test_ic_markets_catalog_contains_all_sixty_one_forex_pairs(): void
    {
        $markets = app(TradingInstrumentCatalog::class)->markets();
        $forex = $markets['forex'];
        $instruments = $forex['instruments'];

        $this->assertCount(61, $instruments);
        $this->assertSame(61, $forex['instrument_count']);
        $this->assertSame('IC Markets', $forex['broker']);
        $this->assertSame('PARTIALLY VERIFIED', $forex['spec_status']);

        foreach ([
            'EURUSD',
            'GBPJPY',
            'EURGBP',
            'AUDCAD',
            'USDCNH',
            'USDTHB',
            'USDSGD',
        ] as $symbol) {
            $this->assertArrayHasKey($symbol, $instruments);
        }
    }

    public function test_ic_markets_pair_categories_match_current_catalog_counts(): void
    {
        $instruments = app(TradingInstrumentCatalog::class)
            ->markets()['forex']['instruments'];

        $counts = [
            'Major' => 0,
            'Minor' => 0,
            'Exotic' => 0,
        ];

        foreach ($instruments as $instrument) {
            $category = $instrument['category'];
            $counts[$category]++;
        }

        $this->assertSame(6, $counts['Major']);
        $this->assertSame(22, $counts['Minor']);
        $this->assertSame(33, $counts['Exotic']);
    }

    public function test_ic_markets_forex_trade_size_profile_is_applied_by_category(): void
    {
        $instruments = app(TradingInstrumentCatalog::class)
            ->markets()['forex']['instruments'];

        $this->assertSame(100000.0, $instruments['EURUSD']['contract_size']);
        $this->assertSame(0.01, $instruments['EURUSD']['min_lot']);
        $this->assertSame(0.01, $instruments['EURUSD']['lot_step']);
        $this->assertSame(200.0, $instruments['EURUSD']['max_lot']);

        $this->assertSame(100000.0, $instruments['EURGBP']['contract_size']);
        $this->assertSame(200.0, $instruments['EURGBP']['max_lot']);

        $this->assertSame(100000.0, $instruments['USDTHB']['contract_size']);
        $this->assertSame(50.0, $instruments['USDTHB']['max_lot']);
    }

    public function test_jpy_quote_pairs_use_standard_jpy_pip_distance(): void
    {
        $instruments = app(TradingInstrumentCatalog::class)
            ->markets()['forex']['instruments'];

        $this->assertSame(0.01, $instruments['GBPJPY']['distance_unit']);
        $this->assertSame(0.001, $instruments['GBPJPY']['tick_size']);
        $this->assertSame(0.0001, $instruments['EURGBP']['distance_unit']);
        $this->assertSame(0.00001, $instruments['EURGBP']['tick_size']);
    }
}
