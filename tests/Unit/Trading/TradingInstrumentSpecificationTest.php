<?php

namespace Tests\Unit\Trading;

use Tests\TestCase;

class TradingInstrumentSpecificationTest extends TestCase
{
    public function test_every_configured_instrument_has_safe_volume_and_distance_values(): void
    {
        $markets = (array) config('trading_instruments.markets', []);

        $this->assertNotEmpty($markets);

        foreach ($markets as $marketKey => $market) {
            $instruments = (array) ($market['instruments'] ?? []);

            $this->assertNotEmpty(
                $instruments,
                sprintf('Market %s must contain at least one instrument.', $marketKey)
            );

            foreach ($instruments as $symbol => $instrument) {
                $contractSize = (float) ($instrument['contract_size'] ?? 0);
                $minLot = (float) ($instrument['min_lot'] ?? 0);
                $maxLot = (float) ($instrument['max_lot'] ?? 0);
                $lotStep = (float) ($instrument['lot_step'] ?? 0);
                $distanceUnit = (float) ($instrument['distance_unit'] ?? 0);

                $this->assertGreaterThan(0, $contractSize, $symbol.' contract size');
                $this->assertGreaterThan(0, $minLot, $symbol.' minimum lot');
                $this->assertGreaterThanOrEqual($minLot, $maxLot, $symbol.' maximum lot');
                $this->assertGreaterThan(0, $lotStep, $symbol.' lot step');
                $this->assertLessThanOrEqual($minLot, $lotStep, $symbol.' lot step must not exceed minimum lot');
                $this->assertGreaterThan(0, $distanceUnit, $symbol.' distance unit');
                $this->assertNotEmpty((string) ($instrument['base_currency'] ?? ''), $symbol.' base currency');
                $this->assertNotEmpty((string) ($instrument['quote_currency'] ?? ''), $symbol.' quote currency');
            }
        }
    }

    public function test_deriv_reference_contains_reviewed_crash_and_boom_size_limits(): void
    {
        $deriv = (array) config('trading_instruments.markets.deriv', []);
        $instruments = (array) ($deriv['instruments'] ?? []);

        $this->assertSame('PARTIALLY VERIFIED', $deriv['spec_status'] ?? null);
        $this->assertSame('2026-09-15', $deriv['spec_verified_at'] ?? null);

        $this->assertSame(475.0, $instruments['BOOM300']['max_lot'] ?? null);
        $this->assertSame(450.0, $instruments['BOOM500']['max_lot'] ?? null);
        $this->assertSame(400.0, $instruments['BOOM600']['max_lot'] ?? null);
        $this->assertSame(200.0, $instruments['BOOM50']['max_lot'] ?? null);

        $this->assertSame(720.0, $instruments['CRASH1000']['max_lot'] ?? null);
        $this->assertSame(120.0, $instruments['CRASH600']['max_lot'] ?? null);
        $this->assertSame(730.0, $instruments['CRASH500']['max_lot'] ?? null);
        $this->assertSame(515.0, $instruments['CRASH300']['max_lot'] ?? null);
        $this->assertSame(1230.0, $instruments['CRASH150']['max_lot'] ?? null);
    }

    public function test_reference_markets_are_not_misrepresented_as_broker_verified(): void
    {
        foreach (['forex', 'commodity', 'crypto'] as $marketKey) {
            $this->assertSame(
                'REFERENCE',
                config('trading_instruments.markets.'.$marketKey.'.spec_status')
            );

            $this->assertNull(
                config('trading_instruments.markets.'.$marketKey.'.spec_verified_at')
            );
        }
    }
}