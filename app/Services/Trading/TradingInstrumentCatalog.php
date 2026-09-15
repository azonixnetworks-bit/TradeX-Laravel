<?php

namespace App\Services\Trading;

class TradingInstrumentCatalog
{
    /** @return array<string, mixed> */
    public function markets(): array
    {
        $markets = (array) config('trading_instruments.markets', []);
        $icForex = (array) config('ic_markets_forex', []);

        if (! (bool) ($icForex['enabled'] ?? false)) {
            return $markets;
        }

        $forex = (array) ($markets['forex'] ?? []);
        $forex['label'] = (string) ($icForex['label'] ?? 'Forex');
        $forex['description'] = (string) ($icForex['description'] ?? ($forex['description'] ?? ''));
        $forex['spec_status'] = (string) ($icForex['spec_status'] ?? ($forex['spec_status'] ?? 'REFERENCE'));
        $forex['spec_source'] = (string) ($icForex['spec_source'] ?? ($forex['spec_source'] ?? 'TradeX reference'));
        $forex['spec_verified_at'] = $icForex['spec_verified_at'] ?? ($forex['spec_verified_at'] ?? null);
        $forex['broker'] = (string) ($icForex['broker'] ?? 'IC Markets');
        $forex['instrument_count'] = (int) ($icForex['instrument_count'] ?? count((array) ($icForex['instruments'] ?? [])));
        $forex['instruments'] = (array) ($icForex['instruments'] ?? []);

        $markets['forex'] = $forex;

        return $markets;
    }
}
