<?php

namespace App\Services\Trading;

class TradingInstrumentCatalog
{
    /** @return array<string, mixed> */
    public function markets(): array
    {
        $markets = (array) config('trading_instruments.markets', []);

        $markets = $this->applyBrokerCatalog(
            $markets,
            'forex',
            (array) config('ic_markets_forex', [])
        );

        $markets = $this->applyBrokerCatalog(
            $markets,
            'commodity',
            (array) config('ic_markets_commodities', [])
        );

        $markets = $this->applyBrokerCatalog(
            $markets,
            'crypto',
            (array) config('ic_markets_crypto', [])
        );

        return $this->applyBrokerCatalog(
            $markets,
            'binance_futures',
            (array) config('binance_futures', [])
        );
    }

    /**
     * @param array<string, mixed> $markets
     * @param array<string, mixed> $catalog
     * @return array<string, mixed>
     */
    private function applyBrokerCatalog(
        array $markets,
        string $marketKey,
        array $catalog
    ): array {
        if (! (bool) ($catalog['enabled'] ?? false)) {
            return $markets;
        }

        $market = (array) ($markets[$marketKey] ?? []);
        $instruments = (array) ($catalog['instruments'] ?? []);

        $market['label'] = (string) ($catalog['label'] ?? ($market['label'] ?? ucfirst($marketKey)));
        $market['description'] = (string) ($catalog['description'] ?? ($market['description'] ?? ''));
        $market['spec_status'] = (string) ($catalog['spec_status'] ?? ($market['spec_status'] ?? 'REFERENCE'));
        $market['spec_source'] = (string) ($catalog['spec_source'] ?? ($market['spec_source'] ?? 'TradeX reference'));
        $market['spec_verified_at'] = $catalog['spec_verified_at'] ?? ($market['spec_verified_at'] ?? null);
        $market['broker'] = (string) ($catalog['broker'] ?? ($market['broker'] ?? 'IC Markets'));
        $market['instrument_count'] = (int) ($catalog['instrument_count'] ?? count($instruments));
        $market['instruments'] = $instruments;

        foreach (['calculation_modes', 'leverage'] as $metadataKey) {
            if (array_key_exists($metadataKey, $catalog)) {
                $market[$metadataKey] = $catalog[$metadataKey];
            }
        }

        $markets[$marketKey] = $market;

        return $markets;
    }
}
