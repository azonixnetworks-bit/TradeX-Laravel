<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwelveDataForexProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:twelve-data-forex:health';

    public function name(): string
    {
        return 'TWELVE_DATA';
    }

    public function market(): string
    {
        return 'FOREX';
    }

    public function configured(): bool
    {
        return (bool) config(
            'tradex_screener.enabled',
            true
        ) && (bool) config(
            'tradex_screener.providers.twelve_data.enabled',
            false
        ) && trim((string) config(
            'tradex_screener.providers.twelve_data.api_key',
            ''
        )) !== '';
    }

    public function timeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array {
        if (! $this->configured()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'Forex market data provider is not configured.'
            );
        }

        $symbols = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $symbol): string =>
                    trim((string) $symbol),
                $symbols
            ),
            static fn (string $symbol): bool => $symbol !== ''
        )));

        if ($symbols === []) {
            return [];
        }

        $batchSize = max(
            1,
            min(
                120,
                (int) config(
                    'tradex_screener.providers.twelve_data.batch_size',
                    10
                )
            )
        );

        $result = [];
        $startedAt = microtime(true);

        try {
            foreach (array_chunk($symbols, $batchSize) as $chunk) {
                $response = $this->request(
                    $chunk,
                    $interval,
                    $outputSize
                );

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_RESPONSE',
                        'Forex market data provider returned an invalid response.'
                    );
                }

                $result = array_merge(
                    $result,
                    $this->normalisePayload(
                        $payload,
                        $chunk
                    )
                );
            }
        } catch (ScreenerProviderException $exception) {
            $this->markFailure(
                $exception->providerCode
            );

            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            $this->markFailure(
                'SCREENER_PROVIDER_UNAVAILABLE'
            );

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Forex market data is temporarily unavailable.'
            );
        }

        $this->markSuccess(
            (int) round(
                (microtime(true) - $startedAt) * 1000
            )
        );

        return $result;
    }

    public function health(): array
    {
        $cached = Cache::get(
            self::HEALTH_CACHE_KEY,
            []
        );

        $configured = $this->configured();

        return [
            'provider' => $this->name(),
            'market' => $this->market(),
            'enabled' => (bool) config(
                'tradex_screener.providers.twelve_data.enabled',
                false
            ),
            'configured' => $configured,
            'status' => $configured
                ? (string) ($cached['status'] ?? 'UNKNOWN')
                : 'UNCONFIGURED',
            'last_success_at_utc' =>
                $cached['last_success_at_utc'] ?? null,
            'last_failure_at_utc' =>
                $cached['last_failure_at_utc'] ?? null,
            'last_error_code' =>
                $cached['last_error_code'] ?? null,
            'last_latency_ms' =>
                $cached['last_latency_ms'] ?? null,
            'base_host' => parse_url(
                (string) config(
                    'tradex_screener.providers.twelve_data.base_url',
                    'https://api.twelvedata.com'
                ),
                PHP_URL_HOST
            ),
        ];
    }

    /**
     * @param  array<int, string>  $symbols
     */
    private function request(
        array $symbols,
        string $interval,
        int $outputSize
    ): Response {
        $baseUrl = rtrim(
            (string) config(
                'tradex_screener.providers.twelve_data.base_url',
                'https://api.twelvedata.com'
            ),
            '/'
        );

        $timeout = max(
            2,
            min(
                30,
                (int) config(
                    'tradex_screener.providers.twelve_data.timeout_seconds',
                    10
                )
            )
        );

        $connectTimeout = max(
            1,
            min(
                $timeout,
                (int) config(
                    'tradex_screener.providers.twelve_data.connect_timeout_seconds',
                    5
                )
            )
        );

        try {
            $response = Http::acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->get(
                    $baseUrl.'/time_series',
                    [
                        'symbol' => implode(',', $symbols),
                        'interval' => $interval,
                        'outputsize' => max(
                            2,
                            min(5000, $outputSize)
                        ),
                        'order' => 'ASC',
                        'timezone' => 'UTC',
                        'apikey' => (string) config(
                            'tradex_screener.providers.twelve_data.api_key'
                        ),
                    ]
                );
        } catch (ConnectionException) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Forex market data is temporarily unavailable.'
            );
        }

        if (! $response->successful()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_HTTP_ERROR',
                'Forex market data is temporarily unavailable.'
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $requestedSymbols
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function normalisePayload(
        array $payload,
        array $requestedSymbols
    ): array {
        if (($payload['status'] ?? null) === 'error') {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_REJECTED_REQUEST',
                'Forex market data provider rejected the request.'
            );
        }

        if (array_key_exists('values', $payload)) {
            $symbol = (string) (
                $payload['meta']['symbol']
                ?? $requestedSymbols[0]
                ?? ''
            );

            return $symbol !== ''
                ? [
                    $symbol => $this->normaliseValues(
                        $payload['values'] ?? []
                    ),
                ]
                : [];
        }

        $normalised = [];

        foreach ($requestedSymbols as $requestedSymbol) {
            $series = $payload[$requestedSymbol] ?? null;

            if (! is_array($series)) {
                continue;
            }

            if (($series['status'] ?? null) === 'error') {
                continue;
            }

            $symbol = (string) (
                $series['meta']['symbol']
                ?? $requestedSymbol
            );

            $normalised[$symbol] = $this->normaliseValues(
                $series['values'] ?? []
            );
        }

        if ($normalised === []) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'Forex market data provider returned no usable data.'
            );
        }

        return $normalised;
    }

    /**
     * @param  mixed  $values
     * @return array<int, array<string, mixed>>
     */
    private function normaliseValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $candles = [];

        foreach ($values as $value) {
            if (
                ! is_array($value)
                || ! isset(
                    $value['datetime'],
                    $value['open'],
                    $value['high'],
                    $value['low'],
                    $value['close']
                )
            ) {
                continue;
            }

            try {
                $datetime = CarbonImmutable::parse(
                    (string) $value['datetime'],
                    'UTC'
                )->utc();
            } catch (Throwable) {
                continue;
            }

            $candles[] = [
                'datetime_utc' =>
                    $datetime->toIso8601String(),
                'open' => (float) $value['open'],
                'high' => (float) $value['high'],
                'low' => (float) $value['low'],
                'close' => (float) $value['close'],
                'volume' => isset($value['volume'])
                    && $value['volume'] !== ''
                        ? (float) $value['volume']
                        : null,
            ];
        }

        usort(
            $candles,
            static fn (array $left, array $right): int =>
                strcmp(
                    (string) $left['datetime_utc'],
                    (string) $right['datetime_utc']
                )
        );

        return $candles;
    }

    private function markSuccess(int $latencyMs): void
    {
        $previous = Cache::get(
            self::HEALTH_CACHE_KEY,
            []
        );

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => 'HEALTHY',
                    'last_success_at_utc' =>
                        now('UTC')->toIso8601String(),
                    'last_error_code' => null,
                    'last_latency_ms' => $latencyMs,
                ]
            ),
            now()->addDay()
        );
    }

    private function markFailure(string $errorCode): void
    {
        $previous = Cache::get(
            self::HEALTH_CACHE_KEY,
            []
        );

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => 'DEGRADED',
                    'last_failure_at_utc' =>
                        now('UTC')->toIso8601String(),
                    'last_error_code' => $errorCode,
                ]
            ),
            now()->addDay()
        );
    }
}
