<?php

namespace App\Services\Screener;

use App\Services\Trading\BinanceFuturesExchangeInfoService;

class BinanceFuturesCatalogService
{
    /** @return array{symbols: array<int, array<string, mixed>>, source: string, fetched_at_utc: ?string} */
    public function catalog(bool $allowRemote = true): array
    {
        $market = $allowRemote
            ? app(BinanceFuturesExchangeInfoService::class)->hydrateMarket((array) config('binance_futures', []))
            : (array) config('binance_futures', []);

        $symbols = [];

        foreach ((array) ($market['instruments'] ?? []) as $symbol => $instrument) {
            if (! is_array($instrument)) {
                continue;
            }

            $symbol = strtoupper(trim((string) $symbol));
            $base = strtoupper(trim((string) ($instrument['base_currency'] ?? '')));
            $quote = strtoupper(trim((string) ($instrument['quote_currency'] ?? '')));

            if ($symbol === '' || $base === '' || $quote !== 'USDT') {
                continue;
            }

            $symbols[] = [
                'symbol' => $symbol,
                'provider_symbol' => $symbol,
                'name' => (string) ($instrument['name'] ?? $base.' / '.$quote.' Perpetual'),
                'base_currency' => $base,
                'quote_currency' => $quote,
                'enabled' => true,
                'logo_url' => 'https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/'.strtolower($base).'.png',
            ];
        }

        usort($symbols, static fn (array $left, array $right): int => strnatcasecmp($left['symbol'], $right['symbol']));

        return [
            'symbols' => $symbols,
            'source' => (string) ($market['catalog_source'] ?? 'TRADEX_FALLBACK'),
            'fetched_at_utc' => $market['catalog_fetched_at_utc'] ?? null,
        ];
    }
}
