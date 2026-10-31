<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CTraderCommodityCatalogService
{
    private const CACHE_KEY = 'tradex:screener:ctrader-commodity-catalog:v1';

    public function catalog(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->acceptJson()
                ->get($this->baseUrl().'/api/commodities/prices');

            if (! $response->successful()) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'cTrader commodity catalog returned an HTTP error.'
                );
            }

            $payload = $response->json();
            $items = is_array($payload) ? ($payload['data'] ?? []) : [];
            $symbols = [];

            foreach (is_array($items) ? $items : [] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $symbol = trim((string) ($item['symbol'] ?? ''));

                if ($symbol === '') {
                    continue;
                }

                $symbols[] = [
                    'symbol' => $symbol,
                    'provider_symbol' => $symbol,
                    'name' => $symbol,
                    'category' => (string) ($item['category'] ?? 'Commodity'),
                    'base_currency' => '',
                    'quote_currency' => '',
                    'logo_url' => '',
                    'enabled' => true,
                ];
            }

            if ($symbols === []) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'cTrader commodity service returned no symbols.'
                );
            }

            usort(
                $symbols,
                static fn (array $left, array $right): int => strcasecmp(
                    (string) $left['symbol'],
                    (string) $right['symbol']
                )
            );

            $catalog = [
                'provider' => 'CTRADER_COMMODITY',
                'source' => 'TRADEX_MARKET_DATA_FASTAPI',
                'fetched_at_utc' => now('UTC')->toIso8601String(),
                'count' => count($symbols),
                'symbols' => $symbols,
            ];

            Cache::put(self::CACHE_KEY, $catalog, now()->addSeconds(60));

            return $catalog;
        } catch (ScreenerProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'cTrader commodity catalog is temporarily unavailable.'
            );
        }
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'tradex_screener.providers.commodity_ctrader.base_url',
                'http://127.0.0.1:8001'
            ),
            '/'
        );
    }

    private function timeout(): int
    {
        return max(2, min(30, (int) config(
            'tradex_screener.providers.commodity_ctrader.timeout_seconds',
            10
        )));
    }

    private function connectTimeout(): int
    {
        return max(1, min(10, (int) config(
            'tradex_screener.providers.commodity_ctrader.connect_timeout_seconds',
            5
        )));
    }
}
