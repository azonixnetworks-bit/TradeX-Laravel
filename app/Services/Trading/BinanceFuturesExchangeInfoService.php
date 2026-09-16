<?php

namespace App\Services\Trading;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class BinanceFuturesExchangeInfoService
{
    private const CACHE_KEY = 'tradex:binance-futures:exchange-info:v1';
    private const STALE_CACHE_KEY = 'tradex:binance-futures:exchange-info:stale:v1';

    /**
     * Hydrate the Binance Futures market with current public exchangeInfo symbols.
     * Falls back to cached/stale/configured instruments when Binance is unavailable.
     *
     * @param  array<string, mixed>  $market
     * @return array<string, mixed>
     */
    public function hydrateMarket(array $market): array
    {
        $fallbackInstruments = (array) ($market['instruments'] ?? []);
        $settings = (array) config('binance_futures.live_catalog', []);

        if (! (bool) ($settings['enabled'] ?? true)) {
            return $this->applyCatalog(
                $market,
                $fallbackInstruments,
                'TRADEX_FALLBACK',
                null,
                null
            );
        }

        if (app()->environment('testing') && ! (bool) ($settings['allow_testing'] ?? false)) {
            return $this->applyCatalog(
                $market,
                $fallbackInstruments,
                'TRADEX_FALLBACK_TEST',
                null,
                null
            );
        }

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && ! empty($cached['instruments']) && is_array($cached['instruments'])) {
            return $this->applyCatalog(
                $market,
                $cached['instruments'],
                'BINANCE_CACHE',
                $cached['fetched_at_utc'] ?? null,
                $cached['server_time'] ?? null
            );
        }

        try {
            $payload = $this->requestExchangeInfo();
            $instruments = $this->normaliseExchangeInfo($payload);

            if ($instruments !== []) {
                $fetchedAt = now('UTC')->toIso8601String();
                $resolved = [
                    'instruments' => $instruments,
                    'fetched_at_utc' => $fetchedAt,
                    'server_time' => isset($payload['serverTime']) && is_numeric($payload['serverTime'])
                        ? (int) $payload['serverTime']
                        : null,
                ];

                $cacheSeconds = max(60, min(3600, (int) ($settings['cache_seconds'] ?? 600)));
                $staleSeconds = max($cacheSeconds, min(604800, (int) ($settings['stale_cache_seconds'] ?? 86400)));

                Cache::put(self::CACHE_KEY, $resolved, now()->addSeconds($cacheSeconds));
                Cache::put(self::STALE_CACHE_KEY, $resolved, now()->addSeconds($staleSeconds));

                return $this->applyCatalog(
                    $market,
                    $instruments,
                    'BINANCE_LIVE',
                    $fetchedAt,
                    $resolved['server_time']
                );
            }
        } catch (Throwable $exception) {
            // The calculator must remain usable when Binance public market data is unavailable.
            report($exception);
        }

        $stale = Cache::get(self::STALE_CACHE_KEY);

        if (is_array($stale) && ! empty($stale['instruments']) && is_array($stale['instruments'])) {
            return $this->applyCatalog(
                $market,
                $stale['instruments'],
                'BINANCE_STALE_CACHE',
                $stale['fetched_at_utc'] ?? null,
                $stale['server_time'] ?? null
            );
        }

        return $this->applyCatalog(
            $market,
            $fallbackInstruments,
            'TRADEX_FALLBACK',
            null,
            null
        );
    }

    /**
     * Convert Binance exchangeInfo into TradeX calculator instruments.
     * Only active USDT-quoted USDⓈ-M perpetual contracts are included.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    public function normaliseExchangeInfo(array $payload): array
    {
        $settings = (array) config('binance_futures.live_catalog', []);
        $quoteAsset = strtoupper((string) ($settings['quote_asset'] ?? 'USDT'));
        $contractType = strtoupper((string) ($settings['contract_type'] ?? 'PERPETUAL'));
        $tradingStatus = strtoupper((string) ($settings['trading_status'] ?? 'TRADING'));
        $source = 'Binance public USDⓈ-M Futures exchangeInfo live trading rules; leverage and maintenance-margin tiers remain account/notional dependent';
        $reviewedAt = now('UTC')->toDateString();
        $instruments = [];

        foreach ((array) ($payload['symbols'] ?? []) as $symbolData) {
            if (! is_array($symbolData)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($symbolData['symbol'] ?? '')));
            $baseAsset = strtoupper(trim((string) ($symbolData['baseAsset'] ?? '')));
            $symbolQuote = strtoupper(trim((string) ($symbolData['quoteAsset'] ?? '')));
            $symbolContract = strtoupper(trim((string) ($symbolData['contractType'] ?? '')));
            $status = strtoupper(trim((string) ($symbolData['status'] ?? '')));

            if (
                $symbol === ''
                || $baseAsset === ''
                || $symbolQuote !== $quoteAsset
                || $symbolContract !== $contractType
                || $status !== $tradingStatus
            ) {
                continue;
            }

            $filters = $this->indexFilters((array) ($symbolData['filters'] ?? []));
            $priceFilter = (array) ($filters['PRICE_FILTER'] ?? []);
            $lotFilter = (array) ($filters['LOT_SIZE'] ?? []);
            $marketLotFilter = (array) ($filters['MARKET_LOT_SIZE'] ?? []);
            $minNotionalFilter = (array) ($filters['MIN_NOTIONAL'] ?? $filters['NOTIONAL'] ?? []);

            $tickSize = $this->positiveFloat($priceFilter['tickSize'] ?? null);
            $minQty = $this->positiveFloat($lotFilter['minQty'] ?? null);
            $maxQty = $this->positiveFloat($lotFilter['maxQty'] ?? null);
            $stepSize = $this->positiveFloat($lotFilter['stepSize'] ?? null);

            if ($tickSize === null || $minQty === null || $maxQty === null || $stepSize === null) {
                continue;
            }

            $instruments[$symbol] = [
                'name' => sprintf('%s / %s Perpetual', $baseAsset, $symbolQuote),
                'category' => 'USDⓈ-M Perpetual',
                'broker' => 'Binance',
                'contract_size' => 1.0,
                'base_currency' => $baseAsset,
                'quote_currency' => $symbolQuote,
                'min_lot' => $minQty,
                'max_lot' => $maxQty,
                'lot_step' => $stepSize,
                'tick_size' => $tickSize,
                'distance_unit' => $tickSize,
                'distance_label' => 'price units',
                'provider_symbol' => $symbol,
                'provider_status' => $status,
                'contract_type' => $symbolContract,
                'margin_asset' => strtoupper((string) ($symbolData['marginAsset'] ?? $symbolQuote)),
                'onboard_date' => isset($symbolData['onboardDate']) && is_numeric($symbolData['onboardDate'])
                    ? (int) $symbolData['onboardDate']
                    : null,
                'market_min_lot' => $this->positiveFloat($marketLotFilter['minQty'] ?? null),
                'market_max_lot' => $this->positiveFloat($marketLotFilter['maxQty'] ?? null),
                'market_lot_step' => $this->positiveFloat($marketLotFilter['stepSize'] ?? null),
                'min_notional' => $this->positiveFloat(
                    $minNotionalFilter['notional']
                    ?? $minNotionalFilter['minNotional']
                    ?? null
                ),
                'spec_profile' => 'Binance live exchangeInfo USDⓈ-M perpetual filters',
                'spec_status' => 'LIVE FILTERS / PARTIALLY VERIFIED',
                'spec_source' => $source,
                'spec_verified_at' => $reviewedAt,
            ];
        }

        ksort($instruments, SORT_NATURAL);

        return $instruments;
    }

    /** @return array<string, mixed> */
    private function requestExchangeInfo(): array
    {
        $settings = (array) config('binance_futures.live_catalog', []);
        $baseUrl = rtrim((string) ($settings['base_url'] ?? 'https://fapi.binance.com'), '/');
        $timeout = max(2, min(15, (int) ($settings['timeout_seconds'] ?? 5)));
        $connectTimeout = max(1, min($timeout, (int) ($settings['connect_timeout_seconds'] ?? 2)));

        try {
            $response = Http::acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->get($baseUrl.'/fapi/v1/exchangeInfo');
        } catch (ConnectionException $exception) {
            throw $exception;
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Binance Futures exchangeInfo request failed with HTTP '.$response->status().'.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Binance Futures exchangeInfo returned an invalid response.');
        }

        return $payload;
    }

    /**
     * @param  array<int, mixed>  $filters
     * @return array<string, array<string, mixed>>
     */
    private function indexFilters(array $filters): array
    {
        $indexed = [];

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $type = strtoupper(trim((string) ($filter['filterType'] ?? '')));

            if ($type !== '') {
                $indexed[$type] = $filter;
            }
        }

        return $indexed;
    }

    private function positiveFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) && $number > 0 ? $number : null;
    }

    /**
     * @param  array<string, mixed>  $market
     * @param  array<string, array<string, mixed>>  $instruments
     * @return array<string, mixed>
     */
    private function applyCatalog(
        array $market,
        array $instruments,
        string $source,
        mixed $fetchedAt,
        mixed $serverTime
    ): array {
        $market['instruments'] = $instruments;
        $market['instrument_count'] = count($instruments);
        $market['catalog_source'] = $source;
        $market['catalog_fetched_at_utc'] = is_string($fetchedAt) ? $fetchedAt : null;
        $market['catalog_server_time'] = is_numeric($serverTime) ? (int) $serverTime : null;
        $market['live_catalog_active'] = in_array($source, ['BINANCE_LIVE', 'BINANCE_CACHE', 'BINANCE_STALE_CACHE'], true);

        if ($source === 'BINANCE_LIVE') {
            $market['description'] = 'Live Binance USDⓈ-M USDT perpetual contracts and exchangeInfo trading filters. Leverage and maintenance-margin tiers remain live account/notional dependent.';
        } elseif (in_array($source, ['BINANCE_CACHE', 'BINANCE_STALE_CACHE'], true)) {
            $market['description'] = 'Cached Binance USDⓈ-M USDT perpetual contracts and exchangeInfo trading filters. Live leverage and maintenance-margin tiers remain account/notional dependent.';
        } else {
            $market['description'] = 'Binance live catalog is unavailable or disabled; TradeX emergency fallback symbols are shown. Confirm live Binance trading filters before execution.';
        }

        return $market;
    }
}
