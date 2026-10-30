<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class DerivMarketDataProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:deriv:health';

    public function name(): string
    {
        return 'DERIV';
    }

    public function market(): string
    {
        return 'DERIV';
    }

    public function configured(): bool
    {
        return
            (bool) config(
                'tradex_screener.enabled',
                true
            )
            &&
            (bool) config(
                'tradex_screener.providers.deriv.enabled',
                false
            )
            &&
            trim(
                (string) config(
                    'tradex_screener.providers.deriv.base_url',
                    ''
                )
            ) !== '';
    }

    public function timeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array {
        if (! $this->configured()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'cTrader Deriv market-data service is not configured.'
            );
        }

        $symbols = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn (mixed $symbol): string => trim((string) $symbol),
                        $symbols
                    ),
                    static fn (string $symbol): bool => $symbol !== ''
                )
            )
        );

        if ($symbols === []) {
            return [];
        }

        $timeframe = $this->timeframe(
            $interval
        );

        $count = max(
            2,
            min(
                500,
                (int) config(
                    'tradex_screener.providers.deriv.output_size',
                    $outputSize
                )
            )
        );

        $startedAt = microtime(true);

        $result = [];
        $failed = 0;

        foreach ($symbols as $symbol) {
            try {
                $response = Http::connectTimeout(
                    $this->connectTimeout()
                )
                    ->timeout($this->timeout())
                    ->acceptJson()
                    ->get(
                        $this->baseUrl()
                        .'/api/deriv/candles/'
                        .rawurlencode($symbol),
                        [
                            'timeframe' => $timeframe,
                            'count' => $count,
                        ]
                    );

                if (! $response->successful()) {
                    $failed++;

                    continue;
                }

                $payload = $response->json();

                $candles = is_array($payload)
                    ? ($payload['data'] ?? [])
                    : [];

                $normalised = $this->normaliseCandles(
                    $candles
                );

                if ($normalised !== []) {
                    $result[$symbol] =
                        $normalised;
                } else {
                    $failed++;
                }

            } catch (Throwable $exception) {
                report($exception);

                $failed++;
            }
        }

        if ($result === []) {
            $this->markFailure(
                'SCREENER_PROVIDER_NO_DATA'
            );

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'cTrader Deriv service returned no usable screener data.'
            );
        }

        $this->markSuccess(
            (int) round(
                (microtime(true) - $startedAt)
                * 1000
            ),
            $failed > 0
                ? 'SCREENER_PROVIDER_PARTIAL_DATA'
                : null
        );

        return $result;
    }

    /** @return array{price: float, digits: int, observed_at_utc: string|null} */
    public function livePrice(string $symbol, string $side = 'BUY'): array
    {
        if (! $this->configured()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'cTrader Deriv market-data service is not configured.'
            );
        }

        $startedAt = microtime(true);

        try {
            $response = Http::connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->acceptJson()
                ->get($this->baseUrl().'/api/deriv/price/'.rawurlencode(trim($symbol)));

            $payload = $response->successful() ? $response->json() : null;
            if (! is_array($payload)) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'Live Deriv price is temporarily unavailable for this symbol.'
                );
            }

            $side = strtoupper(trim($side));
            $primary = $side === 'SELL' ? ($payload['bid'] ?? null) : ($payload['ask'] ?? null);
            $fallback = $side === 'SELL' ? ($payload['ask'] ?? null) : ($payload['bid'] ?? null);
            $price = (float) ($primary ?? $fallback ?? 0);

            if (! is_finite($price) || $price <= 0) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'Live Deriv price is temporarily unavailable for this symbol.'
                );
            }

            $this->markSuccess((int) round((microtime(true) - $startedAt) * 1000), null);

            return [
                'price' => $price,
                'digits' => max(0, min(8, (int) ($payload['digits'] ?? 3))),
                'observed_at_utc' => isset($payload['updated_at'])
                    ? (string) $payload['updated_at']
                    : null,
            ];
        } catch (ScreenerProviderException $exception) {
            $this->markFailure($exception->providerCode);

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailure('SCREENER_PROVIDER_UNAVAILABLE');

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Live Deriv price is temporarily unavailable.'
            );
        }
    }

    public function health(): array
    {
        $cached = Cache::get(
            self::HEALTH_CACHE_KEY,
            []
        );

        $configured = $this->configured();

        return [
            'provider' => 'DERIV',
            'market' => 'DERIV',

            'enabled' => (bool) config(
                'tradex_screener.providers.deriv.enabled',
                false
            ),

            'configured' => $configured,

            'status' => $configured
                ? (string) (
                    $cached['status']
                    ?? 'UNKNOWN'
                )
                : 'UNCONFIGURED',

            'last_success_at_utc' => $cached['last_success_at_utc']
                ?? null,

            'last_failure_at_utc' => $cached['last_failure_at_utc']
                ?? null,

            'last_error_code' => $cached['last_error_code']
                ?? null,

            'last_latency_ms' => $cached['last_latency_ms']
                ?? null,

            'base_host' => parse_url(
                $this->baseUrl(),
                PHP_URL_HOST
            ),

            'authentication_required' => false,
        ];
    }

    private function timeframe(
        string $interval
    ): string {
        $interval = strtolower(
            trim($interval)
        );

        return match ($interval) {
            '300', '5m', '5min' => 'M5',
            '900', '15m', '15min' => 'M15',
            '3600', '1h' => 'H1',
            '14400', '4h' => 'H4',
            '86400', '1d', '1day' => 'D1',

            default => throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_REQUEST',
                'Unsupported cTrader Deriv timeframe.'
            ),
        };
    }

    private function normaliseCandles(
        mixed $values
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $candles = [];

        foreach ($values as $value) {
            if (
                ! is_array($value)
                || ! isset(
                    $value['datetime_utc'],
                    $value['open'],
                    $value['high'],
                    $value['low'],
                    $value['close']
                )
            ) {
                continue;
            }

            $candles[] = [
                'datetime_utc' => (string) $value['datetime_utc'],

                'open' => (float) $value['open'],

                'high' => (float) $value['high'],

                'low' => (float) $value['low'],

                'close' => (float) $value['close'],

                'volume' => isset($value['volume'])
                        ? (float) $value['volume']
                        : null,
            ];
        }

        usort(
            $candles,
            static fn (
                array $left,
                array $right
            ): int => strcmp(
                (string) $left[
                    'datetime_utc'
                ],
                (string) $right[
                    'datetime_utc'
                ]
            )
        );

        return $candles;
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

    private function markSuccess(
        int $latencyMs,
        ?string $warningCode
    ): void {
        Cache::put(
            self::HEALTH_CACHE_KEY,
            [
                'status' => $warningCode === null
                        ? 'HEALTHY'
                        : 'DEGRADED',

                'last_success_at_utc' => now('UTC')
                    ->toIso8601String(),

                'last_error_code' => $warningCode,

                'last_latency_ms' => $latencyMs,
            ],
            now()->addDay()
        );
    }

    private function markFailure(
        string $errorCode
    ): void {
        Cache::put(
            self::HEALTH_CACHE_KEY,
            [
                'status' => 'DEGRADED',

                'last_failure_at_utc' => now('UTC')
                    ->toIso8601String(),

                'last_error_code' => $errorCode,
            ],
            now()->addDay()
        );
    }
}
