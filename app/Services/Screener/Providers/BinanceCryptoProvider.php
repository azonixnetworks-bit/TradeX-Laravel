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

class BinanceCryptoProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:binance-crypto:health';

    public function name(): string
    {
        return 'BINANCE';
    }

    public function market(): string
    {
        return 'CRYPTO';
    }

    public function configured(): bool
    {
        return (bool) config(
            'tradex_screener.enabled',
            true
        ) && (bool) config(
            'tradex_screener.providers.binance.enabled',
            true
        ) && trim((string) config(
            'tradex_screener.providers.binance.base_url',
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
                'Crypto market data provider is not configured.'
            );
        }

        $symbols = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $symbol): string =>
                    strtoupper(trim((string) $symbol)),
                $symbols
            ),
            static fn (string $symbol): bool => $symbol !== ''
        )));

        if ($symbols === []) {
            return [];
        }

        $result = [];
        $startedAt = microtime(true);

        try {
            foreach ($symbols as $symbol) {
                $response = $this->request(
                    $symbol,
                    $interval,
                    $outputSize
                );

                $payload = $response->json();

                if (! is_array($payload)) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_RESPONSE',
                        'Crypto market data provider returned an invalid response.'
                    );
                }

                $candles = $this->normaliseKlines(
                    $payload
                );

                if ($candles !== []) {
                    $result[$symbol] = $candles;
                }
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
                'Crypto market data is temporarily unavailable.'
            );
        }

        if ($result === []) {
            $this->markFailure(
                'SCREENER_PROVIDER_NO_DATA'
            );

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'Crypto market data provider returned no usable data.'
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
                'tradex_screener.providers.binance.enabled',
                true
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
                    'tradex_screener.providers.binance.base_url',
                    'https://data-api.binance.vision'
                ),
                PHP_URL_HOST
            ),
        ];
    }

    private function request(
        string $symbol,
        string $interval,
        int $outputSize
    ): Response {
        $baseUrl = rtrim(
            (string) config(
                'tradex_screener.providers.binance.base_url',
                'https://data-api.binance.vision'
            ),
            '/'
        );

        $timeout = max(
            2,
            min(
                30,
                (int) config(
                    'tradex_screener.providers.binance.timeout_seconds',
                    10
                )
            )
        );

        $connectTimeout = max(
            1,
            min(
                $timeout,
                (int) config(
                    'tradex_screener.providers.binance.connect_timeout_seconds',
                    5
                )
            )
        );

        try {
            $response = Http::acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->get(
                    $baseUrl.'/api/v3/klines',
                    [
                        'symbol' => $symbol,
                        'interval' => $interval,
                        'limit' => max(
                            2,
                            min(1000, $outputSize)
                        ),
                    ]
                );
        } catch (ConnectionException) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Crypto market data is temporarily unavailable.'
            );
        }

        if (in_array($response->status(), [418, 429], true)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_RATE_LIMITED',
                'Crypto market data provider is temporarily rate limited.'
            );
        }

        if ($response->clientError()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_REJECTED_REQUEST',
                'Crypto market data provider rejected the request.'
            );
        }

        if (! $response->successful()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_HTTP_ERROR',
                'Crypto market data is temporarily unavailable.'
            );
        }

        return $response;
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function normaliseKlines(array $payload): array
    {
        $candles = [];

        foreach ($payload as $value) {
            if (
                ! is_array($value)
                || ! isset(
                    $value[0],
                    $value[1],
                    $value[2],
                    $value[3],
                    $value[4],
                    $value[5]
                )
            ) {
                continue;
            }

            try {
                $datetime = CarbonImmutable::createFromTimestampMs(
                    (int) $value[0],
                    'UTC'
                )->utc();
            } catch (Throwable) {
                continue;
            }

            $candles[] = [
                'datetime_utc' =>
                    $datetime->toIso8601String(),
                'open' => (float) $value[1],
                'high' => (float) $value[2],
                'low' => (float) $value[3],
                'close' => (float) $value[4],
                'volume' => (float) $value[5],
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
