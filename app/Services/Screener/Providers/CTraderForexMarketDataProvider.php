<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class CTraderForexMarketDataProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY = 'tradex:screener:provider:forex_ctrader:health';

    public function name(): string
    {
        return 'CTRADER_FOREX';
    }

    public function market(): string
    {
        return 'FOREX';
    }

    public function configured(): bool
    {
        return (bool) config('tradex_screener.enabled', true)
            && (bool) config('tradex_screener.providers.forex_ctrader.enabled', false)
            && trim((string) config('tradex_screener.providers.forex_ctrader.base_url', '')) !== '';
    }

    public function timeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array {
        if (! $this->configured()) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NOT_CONFIGURED',
                'cTrader Forex market-data service is not configured.'
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

        $timeframe = $this->timeframe($interval);

        $count = max(
            2,
            min(
                500,
                (int) config(
                    'tradex_screener.providers.forex_ctrader.output_size',
                    $outputSize
                )
            )
        );

        $startedAt = microtime(true);
        $result = [];
        $failed = 0;
        $lastFailure = null;

        foreach ($symbols as $symbol) {
            try {
                $cleanSymbol = str_replace(['/', '_', ' '], '', $symbol);
                $response = Http::connectTimeout($this->connectTimeout())
                    ->timeout($this->timeout())
                    ->acceptJson()
                    ->get(
                        $this->baseUrl().'/api/forex/candles/'.rawurlencode($cleanSymbol),
                        [
                            'timeframe' => $timeframe,
                            'count' => $count,
                        ]
                    );

                if (! $response->successful()) {
                    $failed++;
                    $lastFailure = new ScreenerProviderException(
                        'SCREENER_PROVIDER_HTTP_ERROR',
                        'cTrader Forex market data is temporarily unavailable.'
                    );

                    continue;
                }

                $payload = $response->json();

                if (! is_array($payload)) {
                    $failed++;
                    $lastFailure = new ScreenerProviderException(
                        'SCREENER_PROVIDER_INVALID_RESPONSE',
                        'cTrader Forex service returned an invalid response.'
                    );

                    continue;
                }

                $candles = is_array($payload) ? ($payload['data'] ?? []) : [];
                $normalised = $this->normaliseCandles($candles);

                if ($normalised !== []) {
                    $result[$symbol] = $normalised;
                } else {
                    $failed++;
                    $lastFailure = new ScreenerProviderException(
                        'SCREENER_PROVIDER_NO_DATA',
                        'cTrader Forex service returned no usable screener data.'
                    );
                }
            } catch (ConnectionException) {
                $failed++;
                $lastFailure = new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'cTrader Forex market data is temporarily unavailable.'
                );
            } catch (Throwable $exception) {
                report($exception);
                $failed++;
                $lastFailure = new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'cTrader Forex market data is temporarily unavailable.'
                );
            }
        }

        if ($result === []) {
            $failure = $lastFailure ?? new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'cTrader Forex service returned no usable screener data.'
            );

            $this->markFailure($failure->providerCode);

            throw $failure;
        }

        $this->markSuccess(
            (int) round((microtime(true) - $startedAt) * 1000),
            $failed > 0 ? 'SCREENER_PROVIDER_PARTIAL_DATA' : null
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
            'enabled' => (bool) config('tradex_screener.providers.forex_ctrader.enabled', false),
            'configured' => $configured,
            'status' => $configured ? (string) ($cached['status'] ?? 'UNKNOWN') : 'UNCONFIGURED',
            'last_success_at_utc' => $cached['last_success_at_utc'] ?? null,
            'last_failure_at_utc' => $cached['last_failure_at_utc'] ?? null,
            'last_error_code' => $cached['last_error_code'] ?? null,
            'last_latency_ms' => $cached['last_latency_ms'] ?? null,
            'base_host' => parse_url($this->baseUrl(), PHP_URL_HOST),
            'authentication_required' => false,
        ];
    }

    private function timeframe(string $interval): string
    {
        $interval = strtolower(trim($interval));

        return match ($interval) {
            '300', '5m', '5min' => 'M5',
            '900', '15m', '15min' => 'M15',
            '3600', '1h' => 'H1',
            '14400', '4h' => 'H4',
            '86400', '1d', '1day' => 'D1',
            default => 'H1',
        };
    }

    private function normaliseCandles(mixed $values): array
    {
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
                'volume' => isset($value['volume']) ? (float) $value['volume'] : null,
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
                60,
                (int) config(
                    'tradex_screener.providers.forex_ctrader.timeout_seconds',
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
                    'tradex_screener.providers.forex_ctrader.connect_timeout_seconds',
                    5
                )
            )
        );
    }

    private function markSuccess(int $latencyMs, ?string $warningCode): void
    {
        Cache::put(
            self::HEALTH_CACHE_KEY,
            [
                'status' => $warningCode === null ? 'HEALTHY' : 'DEGRADED',
                'last_success_at_utc' => now('UTC')->toIso8601String(),
                'last_error_code' => $warningCode,
                'last_latency_ms' => $latencyMs,
            ],
            now()->addDay()
        );
    }

    private function markFailure(string $errorCode): void
    {
        Cache::put(
            self::HEALTH_CACHE_KEY,
            [
                'status' => 'DEGRADED',
                'last_failure_at_utc' => now('UTC')->toIso8601String(),
                'last_error_code' => $errorCode,
            ],
            now()->addDay()
        );
    }
}
