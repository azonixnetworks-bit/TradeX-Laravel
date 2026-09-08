<?php

namespace App\Services\Screener\Providers;

use App\Contracts\Screener\CTraderJsonTransport;
use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CTraderForexProvider implements MarketDataProvider
{
    private const HEALTH_CACHE_KEY =
        'tradex:screener:provider:ctrader-forex:health';

    private string $lastSource = 'CTRADER_IC_MARKETS';

    public function __construct(
        private readonly CTraderJsonTransport $transport,
        private readonly TwelveDataForexProvider $fallback
    ) {
    }

    public function name(): string
    {
        if (
            ! $this->primaryConfigured()
            && $this->fallbackEnabled()
            && $this->fallback->configured()
        ) {
            return $this->fallback->name();
        }

        return $this->lastSource;
    }

    public function market(): string
    {
        return 'FOREX';
    }

    public function configured(): bool
    {
        return $this->primaryConfigured()
            || (
                $this->fallbackEnabled()
                && $this->fallback->configured()
            );
    }

    public function timeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array {
        if ($this->primaryConfigured()) {
            $startedAt = microtime(true);

            try {
                $series = $this->cTraderTimeSeries(
                    $symbols,
                    $interval,
                    $outputSize
                );

                $this->lastSource = 'CTRADER_IC_MARKETS';
                $this->markSuccess(
                    (int) round((microtime(true) - $startedAt) * 1000)
                );

                return $series;
            } catch (ScreenerProviderException $exception) {
                $this->markFailure($exception->providerCode);

                if (
                    $this->fallbackEnabled()
                    && $this->fallback->configured()
                ) {
                    return $this->fallbackSeries(
                        $symbols,
                        $interval,
                        $outputSize,
                        $exception->providerCode
                    );
                }

                throw $exception;
            } catch (Throwable $exception) {
                report($exception);
                $this->markFailure('SCREENER_PROVIDER_UNAVAILABLE');

                if (
                    $this->fallbackEnabled()
                    && $this->fallback->configured()
                ) {
                    return $this->fallbackSeries(
                        $symbols,
                        $interval,
                        $outputSize,
                        'SCREENER_PROVIDER_UNAVAILABLE'
                    );
                }

                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_UNAVAILABLE',
                    'cTrader Forex market data is temporarily unavailable.'
                );
            }
        }

        if (
            $this->fallbackEnabled()
            && $this->fallback->configured()
        ) {
            return $this->fallbackSeries(
                $symbols,
                $interval,
                $outputSize,
                'SCREENER_CTRADER_NOT_CONFIGURED'
            );
        }

        throw new ScreenerProviderException(
            'SCREENER_PROVIDER_NOT_CONFIGURED',
            'Forex market data provider is not configured.'
        );
    }

    public function health(): array
    {
        $primaryConfigured = $this->primaryConfigured();
        $fallbackConfigured = $this->fallbackEnabled()
            && $this->fallback->configured();

        if (! $primaryConfigured && $fallbackConfigured) {
            $fallbackHealth = $this->fallback->health();

            return [
                ...$fallbackHealth,
                'provider' => $this->fallback->name(),
                'market' => 'FOREX',
                'configured' => true,
                'primary_provider' => 'CTRADER_IC_MARKETS',
                'primary_configured' => false,
                'fallback_configured' => true,
                'fallback_mode' => true,
            ];
        }

        $cached = Cache::get(self::HEALTH_CACHE_KEY, []);

        return [
            'provider' => 'CTRADER_IC_MARKETS',
            'market' => 'FOREX',
            'enabled' => (bool) config(
                'tradex_screener_ctrader.enabled',
                false
            ),
            'configured' => $primaryConfigured,
            'status' => $primaryConfigured
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
            'environment' => strtoupper((string) config(
                'tradex_screener_ctrader.environment',
                'DEMO'
            )),
            'authentication_required' => true,
            'primary_provider' => 'CTRADER_IC_MARKETS',
            'primary_configured' => $primaryConfigured,
            'fallback_provider' => 'TWELVE_DATA',
            'fallback_configured' => $fallbackConfigured,
            'fallback_mode' => (bool) ($cached['fallback_mode'] ?? false),
            'active_provider' => (string) (
                $cached['active_provider']
                ?? ($primaryConfigured ? 'CTRADER_IC_MARKETS' : 'NONE')
            ),
        ];
    }

    private function primaryConfigured(): bool
    {
        $environment = strtoupper(trim((string) config(
            'tradex_screener_ctrader.environment',
            'DEMO'
        )));

        return (bool) config('tradex_screener.enabled', true)
            && (bool) config('tradex_screener_ctrader.enabled', false)
            && in_array($environment, ['DEMO', 'LIVE'], true)
            && trim((string) config(
                'tradex_screener_ctrader.client_id',
                ''
            )) !== ''
            && trim((string) config(
                'tradex_screener_ctrader.client_secret',
                ''
            )) !== ''
            && trim((string) config(
                'tradex_screener_ctrader.access_token',
                ''
            )) !== ''
            && $this->accountId() > 0
            && $this->transport->endpointHost() !== null;
    }

    private function fallbackEnabled(): bool
    {
        return (bool) config(
            'tradex_screener_ctrader.fallback_to_twelve_data',
            true
        );
    }

    private function accountId(): int
    {
        $value = trim((string) config(
            'tradex_screener_ctrader.ctid_trader_account_id',
            ''
        ));

        return ctype_digit($value)
            ? (int) $value
            : 0;
    }

    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function cTraderTimeSeries(
        array $symbols,
        string $interval,
        int $outputSize
    ): array {
        $symbols = array_values(array_unique(array_filter(array_map(
            static fn (mixed $symbol): string => trim((string) $symbol),
            $symbols
        ), static fn (string $symbol): bool => $symbol !== '')));

        if ($symbols === []) {
            return [];
        }

        $period = $this->periodForInterval($interval);
        $symbolMap = $this->symbolMap();
        $resolved = [];

        foreach ($symbols as $providerSymbol) {
            $brokerName = $this->brokerSymbolName($providerSymbol);
            $key = $this->normaliseSymbolName($brokerName);
            $symbolId = $symbolMap[$key] ?? null;

            if (is_int($symbolId) && $symbolId > 0) {
                $resolved[] = [
                    'provider_symbol' => $providerSymbol,
                    'symbol_id' => $symbolId,
                ];
            }
        }

        if ($resolved === []) {
            throw new ScreenerProviderException(
                'SCREENER_CTRADER_SYMBOLS_NOT_FOUND',
                'Configured Forex symbols are not available on the selected cTrader account.'
            );
        }

        $outputSize = max(
            2,
            min(
                5000,
                min(
                    $outputSize,
                    (int) config(
                        'tradex_screener_ctrader.output_size',
                        260
                    )
                )
            )
        );

        $steps = $this->authenticationSteps();
        $spacing = max(
            200,
            min(
                2000,
                (int) config(
                    'tradex_screener_ctrader.historical_request_spacing_ms',
                    225
                )
            )
        );
        $accountId = $this->accountId();
        $toTimestamp = (int) floor(microtime(true) * 1000);

        foreach ($resolved as $index => $item) {
            $steps[] = [
                'request' => [
                    'clientMsgId' =>
                        'tradex-trend-'.$index.'-'.bin2hex(random_bytes(4)),
                    'payloadType' => 2137,
                    'payload' => [
                        'ctidTraderAccountId' => $accountId,
                        'toTimestamp' => $toTimestamp,
                        'period' => $period,
                        'symbolId' => $item['symbol_id'],
                        'count' => $outputSize,
                    ],
                ],
                'expect_payload_type' => 2138,
                'delay_ms' => $spacing,
            ];
        }

        $responses = $this->transport->exchange($steps);
        $trendResponses = array_slice(
            $responses,
            count($this->authenticationSteps())
        );
        $result = [];

        foreach ($resolved as $index => $item) {
            $response = $trendResponses[$index] ?? null;
            $payload = is_array($response)
                && is_array($response['payload'] ?? null)
                    ? $response['payload']
                    : [];
            $candles = $this->normaliseTrendbars(
                $payload['trendbar'] ?? []
            );

            if ($candles !== []) {
                $result[$item['provider_symbol']] = $candles;
            }
        }

        if ($result === []) {
            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_NO_DATA',
                'cTrader returned no usable Forex trendbar data.'
            );
        }

        return $result;
    }

    /** @return array<string, int> */
    private function symbolMap(): array
    {
        $cacheKey = sprintf(
            'tradex:screener:ctrader:symbol-map:v1:%s:%d',
            strtolower((string) config(
                'tradex_screener_ctrader.environment',
                'DEMO'
            )),
            $this->accountId()
        );
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $steps = $this->authenticationSteps();
        $steps[] = [
            'request' => [
                'clientMsgId' =>
                    'tradex-symbols-'.bin2hex(random_bytes(4)),
                'payloadType' => 2114,
                'payload' => [
                    'ctidTraderAccountId' => $this->accountId(),
                    'includeArchivedSymbols' => false,
                ],
            ],
            'expect_payload_type' => 2115,
        ];

        $responses = $this->transport->exchange($steps);
        $response = $responses[array_key_last($responses)] ?? null;
        $payload = is_array($response)
            && is_array($response['payload'] ?? null)
                ? $response['payload']
                : [];
        $map = [];

        foreach ((array) ($payload['symbol'] ?? []) as $symbol) {
            if (! is_array($symbol)) {
                continue;
            }

            if (array_key_exists('enabled', $symbol) && ! (bool) $symbol['enabled']) {
                continue;
            }

            $symbolId = isset($symbol['symbolId'])
                ? (int) $symbol['symbolId']
                : 0;
            $symbolName = trim((string) ($symbol['symbolName'] ?? ''));
            $key = $this->normaliseSymbolName($symbolName);

            if ($symbolId > 0 && $key !== '' && ! isset($map[$key])) {
                $map[$key] = $symbolId;
            }
        }

        if ($map === []) {
            throw new ScreenerProviderException(
                'SCREENER_CTRADER_SYMBOL_LIST_EMPTY',
                'cTrader returned no usable symbols for the selected account.'
            );
        }

        Cache::put(
            $cacheKey,
            $map,
            max(
                300,
                (int) config(
                    'tradex_screener_ctrader.symbol_map_cache_seconds',
                    21600
                )
            )
        );

        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    private function authenticationSteps(): array
    {
        $suffix = bin2hex(random_bytes(4));

        return [
            [
                'request' => [
                    'clientMsgId' => 'tradex-app-auth-'.$suffix,
                    'payloadType' => 2100,
                    'payload' => [
                        'clientId' => (string) config(
                            'tradex_screener_ctrader.client_id'
                        ),
                        'clientSecret' => (string) config(
                            'tradex_screener_ctrader.client_secret'
                        ),
                    ],
                ],
                'expect_payload_type' => 2101,
            ],
            [
                'request' => [
                    'clientMsgId' => 'tradex-account-auth-'.$suffix,
                    'payloadType' => 2102,
                    'payload' => [
                        'ctidTraderAccountId' => $this->accountId(),
                        'accessToken' => (string) config(
                            'tradex_screener_ctrader.access_token'
                        ),
                    ],
                ],
                'expect_payload_type' => 2103,
            ],
        ];
    }

    private function brokerSymbolName(string $providerSymbol): string
    {
        $overrides = (array) config(
            'tradex_screener_ctrader.symbol_overrides',
            []
        );
        $target = strtoupper(trim($providerSymbol));

        foreach ($overrides as $key => $value) {
            if (strtoupper(trim((string) $key)) === $target) {
                return trim((string) $value);
            }
        }

        return $providerSymbol;
    }

    private function normaliseSymbolName(string $symbol): string
    {
        return preg_replace(
            '/[^A-Z0-9]/',
            '',
            strtoupper(trim($symbol))
        ) ?? '';
    }

    private function periodForInterval(string $interval): int
    {
        return match (strtolower(trim($interval))) {
            '5min', '5m', 'm5' => 5,
            '15min', '15m', 'm15' => 7,
            '1h', 'h1' => 9,
            '4h', 'h4' => 10,
            '1day', '1d', 'd1' => 12,
            default => throw new ScreenerProviderException(
                'SCREENER_CTRADER_UNSUPPORTED_TIMEFRAME',
                'The selected timeframe is not supported by cTrader Forex data.'
            ),
        };
    }

    /**
     * @param  mixed  $trendbars
     * @return array<int, array<string, mixed>>
     */
    private function normaliseTrendbars(mixed $trendbars): array
    {
        if (! is_array($trendbars)) {
            return [];
        }

        $candles = [];

        foreach ($trendbars as $bar) {
            if (! is_array($bar) || ! isset($bar['low'])) {
                continue;
            }

            $minutes = isset($bar['utcTimestampInMinutes'])
                ? (int) $bar['utcTimestampInMinutes']
                : 0;

            if ($minutes <= 0) {
                continue;
            }

            $lowRaw = (float) $bar['low'];
            $openRaw = $lowRaw + (float) ($bar['deltaOpen'] ?? 0);
            $closeRaw = $lowRaw + (float) ($bar['deltaClose'] ?? 0);
            $highRaw = $lowRaw + (float) ($bar['deltaHigh'] ?? 0);

            $candles[] = [
                'datetime_utc' => CarbonImmutable::createFromTimestampUTC(
                    $minutes * 60
                )->toIso8601String(),
                'open' => $openRaw / 100000,
                'high' => $highRaw / 100000,
                'low' => $lowRaw / 100000,
                'close' => $closeRaw / 100000,
                'volume' => isset($bar['volume'])
                    ? (float) $bar['volume']
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

    /**
     * @param  array<int, string>  $symbols
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function fallbackSeries(
        array $symbols,
        string $interval,
        int $outputSize,
        string $primaryErrorCode
    ): array {
        try {
            $series = $this->fallback->timeSeries(
                $symbols,
                $interval,
                $outputSize
            );
        } catch (ScreenerProviderException $exception) {
            throw $exception;
        }

        $this->lastSource = $this->fallback->name();
        $this->markFallbackSuccess($primaryErrorCode);

        return $series;
    }

    private function markSuccess(int $latencyMs): void
    {
        $previous = Cache::get(self::HEALTH_CACHE_KEY, []);

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => 'HEALTHY',
                    'last_success_at_utc' => now('UTC')->toIso8601String(),
                    'last_error_code' => null,
                    'last_latency_ms' => $latencyMs,
                    'active_provider' => 'CTRADER_IC_MARKETS',
                    'fallback_mode' => false,
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
                    'active_provider' => 'CTRADER_IC_MARKETS',
                    'fallback_mode' => false,
                ]
            ),
            now()->addDay()
        );
    }

    private function markFallbackSuccess(string $primaryErrorCode): void
    {
        $previous = Cache::get(self::HEALTH_CACHE_KEY, []);

        Cache::put(
            self::HEALTH_CACHE_KEY,
            array_merge(
                is_array($previous) ? $previous : [],
                [
                    'status' => 'DEGRADED',
                    'last_success_at_utc' => now('UTC')->toIso8601String(),
                    'last_error_code' => $primaryErrorCode,
                    'active_provider' => 'TWELVE_DATA',
                    'fallback_mode' => true,
                ]
            ),
            now()->addDay()
        );
    }
}
