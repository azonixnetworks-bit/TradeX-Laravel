<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BinanceSpotCatalogService
{
    private const CACHE_KEY = 'tradex:screener:binance-spot-catalog:v1';

    private const STALE_CACHE_KEY = 'tradex:screener:binance-spot-catalog:stale:v1';

    /** @return array{symbols: array<int, array<string, mixed>>, source: string, fetched_at_utc: ?string} */
    public function catalog(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['symbols'])) {
            return [...$cached, 'source' => 'BINANCE_CACHE'];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get($this->baseUrl().'/api/v3/exchangeInfo');

            if (in_array($response->status(), [418, 429], true)) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_RATE_LIMITED',
                    'Binance symbol catalog is temporarily rate limited.'
                );
            }

            if (! $response->successful() || ! is_array($response->json())) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'Binance symbol catalog is temporarily unavailable.'
                );
            }

            $resolved = [
                'symbols' => $this->normalise((array) $response->json()),
                'fetched_at_utc' => now('UTC')->toIso8601String(),
            ];

            if ($resolved['symbols'] === []) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'Binance returned no active USDT Spot symbols.'
                );
            }

            Cache::put(self::CACHE_KEY, $resolved, now()->addMinutes(10));
            Cache::put(self::STALE_CACHE_KEY, $resolved, now()->addDay());

            return [...$resolved, 'source' => 'BINANCE_LIVE'];
        } catch (ConnectionException) {
            return $this->staleOrFail();
        } catch (ScreenerProviderException) {
            return $this->staleOrFail();
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function normalise(array $payload): array
    {
        $symbols = [];

        foreach ((array) ($payload['symbols'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($item['symbol'] ?? '')));
            $base = strtoupper(trim((string) ($item['baseAsset'] ?? '')));
            $quote = strtoupper(trim((string) ($item['quoteAsset'] ?? '')));
            $status = strtoupper(trim((string) ($item['status'] ?? '')));

            if (
                $symbol === ''
                || $base === ''
                || $quote !== 'USDT'
                || $status !== 'TRADING'
                || ! (bool) ($item['isSpotTradingAllowed'] ?? true)
            ) {
                continue;
            }

            $symbols[] = [
                'symbol' => $symbol,
                'provider_symbol' => $symbol,
                'name' => $base.' / '.$quote,
                'base_currency' => $base,
                'quote_currency' => $quote,
                'enabled' => true,
                'logo_url' => 'https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/'.strtolower($base).'.png',
            ];
        }

        usort($symbols, static fn (array $left, array $right): int => strnatcasecmp($left['symbol'], $right['symbol']));

        return $symbols;
    }

    private function staleOrFail(): array
    {
        $stale = Cache::get(self::STALE_CACHE_KEY);

        if (is_array($stale) && isset($stale['symbols'])) {
            return [...$stale, 'source' => 'BINANCE_STALE_CACHE'];
        }

        $fallback = array_values(array_filter(
            (array) config('tradex_screener.crypto.symbols', []),
            static fn (mixed $symbol): bool => is_array($symbol) && (bool) ($symbol['enabled'] ?? false)
        ));

        if ($fallback !== []) {
            return [
                'symbols' => array_map(static function (array $symbol): array {
                    $base = strtoupper((string) ($symbol['base_currency'] ?? ''));

                    return [
                        ...$symbol,
                        'logo_url' => 'https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/'.strtolower($base).'.png',
                    ];
                }, $fallback),
                'source' => 'TRADEX_FALLBACK',
                'fetched_at_utc' => null,
            ];
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_UNAVAILABLE',
            'Binance symbol catalog is temporarily unavailable.'
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('tradex_screener.providers.binance.base_url', 'https://data-api.binance.vision'), '/');
    }

    private function timeout(): int
    {
        return max(2, min(30, (int) config('tradex_screener.providers.binance.timeout_seconds', 10)));
    }

    private function connectTimeout(): int
    {
        return max(1, min($this->timeout(), (int) config('tradex_screener.providers.binance.connect_timeout_seconds', 5)));
    }
}
