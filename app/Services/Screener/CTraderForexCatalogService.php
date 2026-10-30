<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CTraderForexCatalogService
{
    private const CACHE_KEY = 'tradex:screener:ctrader-forex-catalog:v1';

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
                ->get($this->baseUrl().'/api/forex/prices');

            if (! $response->successful()) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'cTrader Forex market-data service returned an HTTP error.'
                );
            }

            $payload = $response->json();
            $items = is_array($payload) ? ($payload['data'] ?? []) : [];

            if (! is_array($items)) {
                $items = [];
            }

            $symbols = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = trim((string) ($item['symbol'] ?? ''));

                if ($name === '') {
                    continue;
                }

                $symbols[] = [
                    'symbol' => $name,
                    'provider_symbol' => $name,
                    'name' => $name,
                    'category' => (string) ($item['category'] ?? 'Forex'),
                    'base_currency' => strlen($name) >= 6 ? substr($name, 0, 3) : '',
                    'quote_currency' => strlen($name) >= 6 ? substr($name, 3, 3) : '',
                    'logo_url' => '',
                    'enabled' => true,
                ];
            }

            if ($symbols === []) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'cTrader Forex service returned no symbols.'
                );
            }

            usort(
                $symbols,
                static fn (array $a, array $b): int => strcasecmp(
                    (string) $a['symbol'],
                    (string) $b['symbol']
                )
            );

            $catalog = [
                'provider' => 'CTRADER_FOREX',
                'source' => 'CTRADER_FASTAPI',
                'fetched_at_utc' => now('UTC')->toIso8601String(),
                'count' => count($symbols),
                'symbols' => $symbols,
            ];

            Cache::put(
                self::CACHE_KEY,
                $catalog,
                now()->addSeconds(60)
            );

            return $catalog;

        } catch (ScreenerProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'cTrader Forex market-data catalog is temporarily unavailable.'
            );
        }
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'tradex_screener.providers.forex_ctrader.base_url',
                'http://127.0.0.1:8001'
            ),
            '/'
        );
    }

    private function timeout(): int
    {
        return max(
            2,
            min(
                30,
                (int) config(
                    'tradex_screener.providers.forex_ctrader.timeout_seconds',
                    10
                )
            )
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            min(
                10,
                (int) config(
                    'tradex_screener.providers.forex_ctrader.connect_timeout_seconds',
                    5
                )
            )
        );
    }
}
