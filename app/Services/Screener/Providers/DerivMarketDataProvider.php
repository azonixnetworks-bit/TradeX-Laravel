<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DerivMarketDataProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:deriv:health';

    public function __construct(
        private readonly DerivWebSocketTransport $transport
    ) {
    }

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
        return (bool) config(
            'tradex_screener.enabled',
            true
        ) && (bool) config(
            'tradex_screener.providers.deriv.enabled',
            false
        ) && trim((string) config(
            'tradex_screener.providers.deriv.websocket_url',
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
                'Deriv market data provider is not configured.'
            );
        }

        $symbols = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $symbol): string =>
                    trim((string) $symbol),
                $symbols
            ),
            static fn (string $symbol): bool =>
                $symbol !== ''
        )));

        if ($symbols === []) {
            return [];
        }

        $granularity = (int) $interval;

        if ($granularity < 1 || $granularity > 86400) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_INVALID_REQUEST',
                'Deriv screener timeframe is not supported.'
            );
        }

        $count = max(
            2,
            min(5000, $outputSize)
        );

        $requests = [];
        $symbolsByRequestId = [];
        $requestId = 1;

        foreach ($symbols as $symbol) {
            $requests[] = [
                'ticks_history' => $symbol,
                'end' => 'latest',
                'count' => $count,
                'style' => 'candles',
                'granularity' => $granularity,
                'adjust_start_time' => 1,
                'subscribe' => 0,
                'req_id' => $requestId,
            ];

            $symbolsByRequestId[$requestId] = $symbol;
            $requestId++;
        }

        $startedAt = microtime(true);

        try {
            $responses = $this->transport
                ->requestMany($requests);
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
                'Deriv market data is temporarily unavailable.'
            );
        }

        $result = [];
        $rejected = 0;

        foreach ($responses as $response) {
            if (! is_array($response)) {
                continue;
            }

            $id = isset($response['req_id'])
                ? (int) $response['req_id']
                : 0;

            $symbol = $symbolsByRequestId[$id] ?? null;

            if (! is_string($symbol) || $symbol === '') {
                continue;
            }

            if (isset($response['error'])) {
                $rejected++;
                continue;
            }

            $candles = $this->normaliseCandles(
                $response['candles'] ?? []
            );

            if ($candles !== []) {
                $result[$symbol] = $candles;
            }
        }

        if ($result === []) {
            $code = $rejected > 0
                ? 'SCREENER_PROVIDER_REJECTED_REQUEST'
                : 'SCREENER_PROVIDER_NO_DATA';

            $this->markFailure($code);

            throw new ScreenerProviderException(
                $code,
                'Deriv market data provider returned no usable screener data.'
            );
        }

        $this->markSuccess(
            (int) round(
                (microtime(true) - $startedAt) * 1000
            ),
            $rejected > 0
                ? 'SCREENER_PROVIDER_PARTIAL_DATA'
                : null
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
                'tradex_screener.providers.deriv.enabled',
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
            'base_host' => $this->transport->endpointHost(),
            'authentication_required' => false,
        ];
    }

    /**
     * @param  mixed  $values
     * @return array<int, array<string, mixed>>
     */
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
                    $value['epoch'],
                    $value['open'],
                    $value['high'],
                    $value['low'],
                    $value['close']
                )
            ) {
                continue;
            }

            $epoch = filter_var(
                $value['epoch'],
                FILTER_VALIDATE_INT
            );

            if ($epoch === false || $epoch < 1) {
                continue;
            }

            try {
                $datetime = CarbonImmutable::createFromTimestampUTC(
                    (int) $epoch
                );
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
                'volume' => null,
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

    private function markSuccess(
        int $latencyMs,
        ?string $warningCode
    ): void {
        $previous = Cache::get(
            self::HEALTH_CACHE_KEY,
            []
        );

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => $warningCode === null
                        ? 'HEALTHY'
                        : 'DEGRADED',
                    'last_success_at_utc' =>
                        now('UTC')->toIso8601String(),
                    'last_error_code' => $warningCode,
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
