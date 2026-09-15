<?php

namespace Tests\Unit\Trading;

use App\Services\Trading\RiskRewardCalculatorService;
use App\Services\Trading\TradingInstrumentCatalog;
use Tests\TestCase;

class IcMarketsCryptoCatalogTest extends TestCase
{
    public function test_ic_markets_crypto_catalog_contains_current_expanded_fsa_symbols(): void
    {
        $crypto = app(TradingInstrumentCatalog::class)->markets()['crypto'];
        $instruments = $crypto['instruments'];

        $this->assertSame('IC Markets', $crypto['broker']);
        $this->assertSame('PARTIALLY VERIFIED', $crypto['spec_status']);
        $this->assertSame(94, $crypto['instrument_count']);
        $this->assertCount(94, $instruments);

        foreach ([
            'BTCUSD', 'ETHUSD', 'XRPUSD', 'SOLUSD', 'AAVEUSD',
            'TRUMPUSD', 'PENGUUSD', 'FARTCOINUSD', '1000xPEPE',
            'HYPEUSD', 'TAOUSD', 'QNTUSD', 'KAIAUSD',
        ] as $symbol) {
            $this->assertArrayHasKey($symbol, $instruments);
        }
    }

    public function test_crypto_contract_minimum_maximum_and_tick_profiles_match_catalog(): void
    {
        $instruments = app(TradingInstrumentCatalog::class)
            ->markets()['crypto']['instruments'];

        $this->assertSame(1.0, $instruments['BTCUSD']['contract_size']);
        $this->assertSame(0.01, $instruments['BTCUSD']['min_lot']);
        $this->assertSame(10.0, $instruments['BTCUSD']['max_lot']);
        $this->assertSame(0.01, $instruments['BTCUSD']['tick_size']);

        $this->assertSame(1.0, $instruments['XRPUSD']['contract_size']);
        $this->assertSame(100.0, $instruments['XRPUSD']['min_lot']);
        $this->assertSame(1000.0, $instruments['XRPUSD']['max_lot']);
        $this->assertSame(0.0001, $instruments['XRPUSD']['tick_size']);

        $this->assertSame(1000.0, $instruments['TRXUSD']['contract_size']);
        $this->assertSame(1000.0, $instruments['1000xPEPE']['contract_size']);
        $this->assertSame(0.000001, $instruments['1000xPEPE']['tick_size']);
        $this->assertSame(10000.0, $instruments['KAIAUSD']['contract_size']);

        foreach ($instruments as $instrument) {
            $this->assertSame('USD', $instrument['quote_currency']);
            $this->assertSame('Crypto CFD', $instrument['category']);
            $this->assertGreaterThan(0, $instrument['contract_size']);
            $this->assertGreaterThan(0, $instrument['min_lot']);
            $this->assertGreaterThanOrEqual($instrument['min_lot'], $instrument['max_lot']);
            $this->assertGreaterThan(0, $instrument['tick_size']);
        }
    }

    public function test_bitcoin_position_sizing_uses_ic_markets_crypto_profile(): void
    {
        $result = app(RiskRewardCalculatorService::class)->calculate([
            'market' => 'crypto',
            'symbol' => 'BTCUSD',
            'side' => 'BUY',
            'entry' => 60000,
            'stop_loss' => 59000,
            'take_profit' => 62000,
            'risk_amount' => 100,
        ]);

        $this->assertEqualsWithDelta(0.10, $result['recommended_lot'], 0.000001);
        $this->assertEqualsWithDelta(100.00, $result['actual_risk'], 0.01);
        $this->assertEqualsWithDelta(200.00, $result['potential_profit'], 0.01);
        $this->assertSame('USD_QUOTE', $result['quote_to_usd_conversion']['source']);
        $this->assertSame('IC Markets', $result['specification']['broker']);
        $this->assertSame('Crypto CFD', $result['specification']['category']);
    }
}
