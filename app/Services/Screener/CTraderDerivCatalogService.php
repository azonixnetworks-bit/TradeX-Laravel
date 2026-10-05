<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CTraderDerivCatalogService
{
    private const CACHE_KEY =
        'tradex:screener:ctrader-deriv-catalog:v1';

    public function catalog(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::connectTimeout(
                $this->connectTimeout()
            )
                ->timeout($this->timeout())
                ->acceptJson()
                ->get(
                    $this->baseUrl()
                    .'/api/deriv/prices'
                );

            if (! $response->successful()) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'cTrader Deriv market-data service returned an HTTP error.'
                );
            }

            $payload = $response->json();

            $items = is_array($payload)
                ? ($payload['data'] ?? [])
                : [];

            if (! is_array($items)) {
                $items = [];
            }

            $symbols = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = trim(
                    (string) ($item['symbol'] ?? '')
                );

                if ($name === '') {
                    continue;
                }

                $symbols[] = [
                    'symbol' => $name,
                    'provider_symbol' => $name,
                    'name' => $name,
                    'category' => (string) (
                        $item['category'] ?? ''
                    ),
                    'base_currency' => '',
                    'quote_currency' => '',
                    'logo_url' => '',
                    'enabled' => true,
                ];
            }

            if ($symbols === []) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'cTrader Deriv service returned no symbols.'
                );
            }

            usort(
                $symbols,
                static fn (array $a, array $b): int =>
                    strcasecmp(
                        (string) $a['symbol'],
                        (string) $b['symbol']
                    )
            );

            $catalog = [
                'provider' => 'DERIV',
                'source' => 'CTRADER_FASTAPI',
                'fetched_at_utc' =>
                    now('UTC')->toIso8601String(),
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
                'cTrader Deriv market-data service is unavailable.'
            );
        }
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'tradex_screener.providers.deriv.base_url',
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
                60,
                (int) config(
                    'tradex_screener.providers.deriv.timeout_seconds',
                    20
                )
            )
        );
    }

    private function connectTimeout(): int
    {
        return max(
            1,
            min(
                15,
                (int) config(
                    'tradex_screener.providers.deriv.connect_timeout_seconds',
                    5
                )
            )
        );
    }
}