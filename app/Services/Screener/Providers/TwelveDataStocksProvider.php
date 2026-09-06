<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TwelveDataStocksProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:twelve-data-stocks:health';

    public function name(): string
    {
        return 'TWELVE_DATA_STOCKS';
    }

    public function market(): string
    {
        return 'STOCKS';
    }

    public function configured(): bool
    {
        return (bool) config('tradex_screener.enabled', true)
            && (bool) config('tradex_screener_stocks.enabled', true)
            && (bool) config('tradex_screener_stocks.provider.enabled', false)
            && (bool) config(
                'tradex_screener_stocks.provider.external_display_approved',
                false
            )
            && trim((string) config(
                'tradex_screener_stocks.provider.api_key',
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
                'Stocks market data provider is not configured or display rights are not approved.'
            );
        }

        $symbols = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $symbol): string => strtoupper(trim((string) $symbol)),
                $symbols
            ),
            static fn (string $symbol): bool => $symbol !== ''
        )));

        if ($symbols === []) {
            return [];
        }

        $registry = $this->symbolRegistry();
        $result = [];
        $rejected = 0;
        $startedAt = microtime(true);

        try {
            foreach ($symbols as $symbol) {
                $symbolConfig = $registry[$symbol] ?? null;

                if (! is_array($symbolConfig)) {
                    $rejected++;
                    continue;
                }

                $payload = $this->requestSymbol(
                    $symbolConfig,
                    $interval,
                    $outputSize
                );

                if (($payload['status'] ?? null) === 'error') {
                    $rejected++;
                    continue;
                }

                $candles = $this->normaliseValues(
                    $payload['values'] ?? [],
                    $interval,
                    (string) (
                        $payload['meta']['exchange_timezone']
                        ?? $symbolConfig['exchange_timezone']
                        ?? 'UTC'
                    )
                );

                if ($candles !== []) {
                    $result[$symbol] = $candles;
                }
            }
        } catch (ScreenerProviderException $exception) {
            $this->markFailure($exception->providerCode);
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailure('SCREENER_PROVIDER_UNAVAILABLE');

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Stocks market data is temporarily unavailable.'
            );
        }

        if ($result === []) {
            $code = $rejected > 0
                ? 'SCREENER_PROVIDER_REJECTED_REQUEST'
                : 'SCREENER_PROVIDER_NO_DATA';

            $this->markFailure($code);

            throw new ScreenerProviderException(
                $code,
                'Stocks market data provider returned no usable screener data.'
            );
        }

        $this->markSuccess(
            (int) round((microtime(true) - $startedAt) * 1000),
            $rejected > 0 ? 'SCREENER_PROVIDER_PARTIAL_DATA' : null
        );

        return $result;
    }

    public function health(): array
    {
        $cached = Cache::get(self::HEALTH_CACHE_KEY, []);
        $configured = $this->configured();

        return [
            'provider' => $this->name(),
            'market' => $this->market(),
            'enabled' => (bool) config(
                'tradex_screener_stocks.provider.enabled',
                false
            ),
            'configured' => $configured,
            'external_display_approved' => (bool) config(
                'tradex_screener_stocks.provider.external_display_approved',
                false
            ),
            'status' => $configured
                ? (string) ($cached['status'] ?? 'UNKNOWN')
                : 'UNCONFIGURED',
            'last_success_at_utc' => $cached['last_success_at_utc'] ?? null,
            'last_failure_at_utc' => $cached['last_failure_at_utc'] ?? null,
            'last_error_code' => $cached['last_error_code'] ?? null,
            'last_latency_ms' => $cached['last_latency_ms'] ?? null,
            'base_host' => parse_url(
                (string) config(
                    'tradex_screener_stocks.provider.base_url',
                    'https://api.twelvedata.com'
                ),
                PHP_URL_HOST
            ),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function symbolRegistry(): array
    {
        $registry = [];

        foreach ((array) config('tradex_screener_stocks.symbols', []) as $symbol) {
            if (! is_array($symbol) || ! (bool) ($symbol['enabled'] ?? false)) {
                continue;
            }

            $providerSymbol = strtoupper(trim((string) ($symbol['provider_symbol'] ?? '')));

            if ($providerSymbol !== '') {
                $registry[$providerSymbol] = $symbol;
            }
        }

        return $registry;
    }

    /**
     * @param  array<string, mixed>  $symbolConfig
     * @return array<string, mixed>
     */
    private function requestSymbol(
        array $symbolConfig,
        string $interval,
        int $outputSize
    ): array {
        $baseUrl = rtrim(
            (string) config(
                'tradex_screener_stocks.provider.base_url',
                'https://api.twelvedata.com'
            ),
            '/'
        );

        $timeout = max(2, min(30, (int) config(
            'tradex_screener_stocks.provider.timeout_seconds',
            10
        )));

        $connectTimeout = max(1, min($timeout, (int) config(
            'tradex_screener_stocks.provider.connect_timeout_seconds',
            5
        )));

        $query = [
            'symbol' => (string) $symbolConfig['provider_symbol'],
            'interval' => $interval,
            'outputsize' => max(2, min(5000, $outputSize)),
            'order' => 'ASC',
            'timezone' => 'UTC',
            'apikey' => (string) config(
                'tradex_screener_stocks.provider.api_key'
            ),
        ];

        $micCode = trim((string) ($symbolConfig['mic_code'] ?? ''));

        if ($micCode !== '') {
            $query['mic_code'] = $micCode;
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->get($baseUrl.'/time_series', $query);
        } catch (ConnectionException) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Stocks market data is temporarily unavailable.'
            );
        }

        if (! $response->successful()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_HTTP_ERROR',
                'Stocks market data is temporarily unavailable.'
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_RESPONSE',
                'Stocks market data provider returned an invalid response.'
            );
        }

        return $payload;
    }

    /**
     * @param  mixed  $values
     * @return array<int, array<string, mixed>>
     */
    private function normaliseValues(
        mixed $values,
        string $interval,
        string $exchangeTimezone
    ): array {
        if (! is_array($values)) {
            return [];
        }

        $daily = in_array($interval, ['1day', '1week', '1month'], true);
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
                    $daily ? $exchangeTimezone : 'UTC'
                )->utc();
            } catch (Throwable) {
                continue;
            }

            $candles[] = [
                'datetime_utc' => $datetime->toIso8601String(),
                'open' => (float) $value['open'],
                'high' => (float) $value['high'],
                'low' => (float) $value['low'],
                'close' => (float) $value['close'],
                'volume' => isset($value['volume']) && $value['volume'] !== ''
                    ? (float) $value['volume']
                    : null,
            ];
        }

        usort(
            $candles,
            static fn (array $left, array $right): int => strcmp(
                (string) $left['datetime_utc'],
                (string) $right['datetime_utc']
            )
        );

        return $candles;
    }

    private function markSuccess(int $latencyMs, ?string $warningCode): void
    {
        $previous = Cache::get(self::HEALTH_CACHE_KEY, []);

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => $warningCode === null ? 'HEALTHY' : 'DEGRADED',
                    'last_success_at_utc' => now('UTC')->toIso8601String(),
                    'last_error_code' => $warningCode,
                    'last_latency_ms' => $latencyMs,
                ]
            ),
            now()->addDay()
        );
    }

    private function markFailure(string $errorCode): void
    {
        $previous = Cache::get(self::HEALTH_CACHE_KEY, []);

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => 'DEGRADED',
                    'last_failure_at_utc' => now('UTC')->toIso8601String(),
                    'last_error_code' => $errorCode,
                ]
            ),
            now()->addDay()
        );
    }
}
