<?php

namespace App\Services\Screener;

use App\Contracts\Screener\MarketDataProvider;
use App\Exceptions\ScreenerProviderException;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class ScreenerEngine
{
    /**
     * @var array<string, MarketDataProvider>
     */
    private array $providers = [];

    public function __construct(
        iterable $providers,
        private readonly IndicatorEngine $indicatorEngine
    ) {
        foreach ($providers as $provider) {
            if (! $provider instanceof MarketDataProvider) {
                continue;
            }

            $this->providers[
                strtoupper($provider->market())
            ] = $provider;
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function markets(): array
    {
        return (array) config(
            'tradex_screener.markets',
            []
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function timeframes(): array
    {
        return (array) config(
            'tradex_screener.timeframes',
            []
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function symbols(string $market): array
    {
        $market = strtoupper(trim($market));
        $marketConfig = $this->markets()[$market] ?? null;

        if (! is_array($marketConfig)) {
            return [];
        }

        $configKey = strtolower($market);

        return array_values(array_filter(
            (array) config(
                'tradex_screener.'.$configKey.'.symbols',
                []
            ),
            static fn (mixed $symbol): bool =>
                is_array($symbol)
                && (bool) ($symbol['enabled'] ?? false)
        ));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function screen(array $filters): array
    {
        return $this->screenWithSymbols($filters, null);
    }

    /**
     * Screen a bounded, trusted symbol catalog (used by the paginated admin Binance view).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<int, array<string, mixed>>|null  $symbolConfigs
     * @return array<string, mixed>
     */
    public function screenWithSymbols(array $filters, ?array $symbolConfigs): array
    {
        $market = strtoupper(
            trim(
                (string) ($filters['market'] ?? 'FOREX')
            )
        );

        $marketConfig = $this->markets()[$market] ?? null;

        if (
            ! is_array($marketConfig)
            || ! (bool) ($marketConfig['enabled'] ?? false)
        ) {
            throw new InvalidArgumentException(
                'This screener market is not active yet.'
            );
        }

        $provider = $this->providerForMarket(
            $market,
            strtoupper((string) ($filters['crypto_segment'] ?? 'SPOT'))
        );

        $timeframe = strtoupper(
            trim(
                (string) ($filters['timeframe'] ?? 'H1')
            )
        );

        $timeframeConfig = $this->timeframes()[$timeframe] ?? null;

        if (! is_array($timeframeConfig)) {
            throw new InvalidArgumentException(
                'Unsupported screener timeframe.'
            );
        }

        $dataset = $this->dataset(
            $market,
            $timeframe,
            $timeframeConfig,
            $provider,
            $symbolConfigs
        );

        $rows = $this->applyFilters(
            $dataset['rows'],
            $filters
        );

        $rows = $this->sortRows(
            $rows,
            (string) ($filters['sort'] ?? 'symbol'),
            (string) ($filters['order'] ?? 'asc')
        );

        $health = $provider->health();

        return [
            'market' => $market,
            'timeframe' => $timeframe,
            'provider' => $provider->name(),
            'provider_health' => [
                'status' =>
                    $health['status']
                    ?? 'UNKNOWN',
            ],
            'generated_at_utc' =>
                $dataset['generated_at_utc'],
            'cache' => [
                'hit' => $dataset['cache_hit'],
                'stale' => $dataset['stale'],
                'fresh_seconds' => (int) config(
                    'tradex_screener.cache.fresh_seconds',
                    60
                ),
            ],
            'count' => count($rows),
            'results' => array_values($rows),
        ];
    }

    public function health(string $market = 'FOREX'): array
    {
        return $this->providerForMarket(
            strtoupper(trim($market))
        )->health();
    }

    private function providerForMarket(
        string $market,
        string $cryptoSegment = 'SPOT'
    ): MarketDataProvider {
        $providerKey = $market === 'CRYPTO' && $cryptoSegment === 'FUTURES'
            ? 'CRYPTO_FUTURES'
            : $market;
        $provider = $this->providers[$providerKey] ?? null;

        if (! $provider instanceof MarketDataProvider) {
            throw new InvalidArgumentException(
                'No market data provider is registered for this screener market.'
            );
        }

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $timeframeConfig
     * @return array{
     *   generated_at_utc: string,
     *   rows: array<int, array<string, mixed>>,
     *   cache_hit: bool,
     *   stale: bool
     * }
     */
    private function dataset(
        string $market,
        string $timeframe,
        array $timeframeConfig,
        MarketDataProvider $provider,
        ?array $requestedSymbolConfigs = null
    ): array {
        $symbolConfigs = $requestedSymbolConfigs ?? $this->symbols($market);
        $symbolCacheSuffix = $requestedSymbolConfigs === null
            ? ''
            : ':'.substr(hash('sha256', implode(',', array_map(
                static fn (array $symbol): string => (string) ($symbol['provider_symbol'] ?? ''),
                $symbolConfigs
            ))), 0, 16);

        $freshKey = sprintf(
            'tradex:screener:fresh:v1:%s:%s:%s%s',
            $market,
            $provider->name(),
            $timeframe,
            $symbolCacheSuffix
        );

        $staleKey = sprintf(
            'tradex:screener:stale:v1:%s:%s:%s%s',
            $market,
            $provider->name(),
            $timeframe,
            $symbolCacheSuffix
        );

        $fresh = Cache::get($freshKey);

        if (is_array($fresh)) {
            return [
                ...$fresh,
                'cache_hit' => true,
                'stale' => false,
            ];
        }

        try {
            $providerSymbols = array_values(array_map(
                static fn (array $symbol): string =>
                    (string) $symbol['provider_symbol'],
                $symbolConfigs
            ));

            $interval = $this->providerInterval(
                $provider,
                $timeframeConfig
            );

            $series = $provider->timeSeries(
                $providerSymbols,
                $interval,
                $this->providerOutputSize(
                    $provider
                )
            );

            $rows = [];

            foreach ($symbolConfigs as $symbolConfig) {
                $providerSymbol = (string) $symbolConfig['provider_symbol'];
                $candles = $series[$providerSymbol] ?? [];

                if ($candles === []) {
                    continue;
                }

                $analysis = $this->indicatorEngine
                    ->analyse($candles);

                $rows[] = [
                    'symbol' =>
                        (string) $symbolConfig['symbol'],
                    'provider_symbol' =>
                        $providerSymbol,
                    'name' =>
                        (string) ($symbolConfig['name'] ?? $symbolConfig['symbol']),
                    'base_currency' =>
                        (string) ($symbolConfig['base_currency'] ?? ''),
                    'quote_currency' =>
                        (string) ($symbolConfig['quote_currency'] ?? ''),
                    'logo_url' =>
                        (string) ($symbolConfig['logo_url'] ?? ''),
                    ...$analysis,
                    'volatility' => $this->volatilityLabel(
                        $analysis['atr_percent'] ?? null
                    ),
                ];
            }

            if ($rows === []) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_NO_DATA',
                    'Market data provider returned no usable screener data.'
                );
            }

            $dataset = [
                'generated_at_utc' =>
                    now('UTC')->toIso8601String(),
                'rows' => $rows,
            ];

            Cache::put(
                $freshKey,
                $dataset,
                max(
                    5,
                    (int) config(
                        'tradex_screener.cache.fresh_seconds',
                        60
                    )
                )
            );

            Cache::put(
                $staleKey,
                $dataset,
                max(
                    60,
                    (int) config(
                        'tradex_screener.cache.stale_seconds',
                        900
                    )
                )
            );

            return [
                ...$dataset,
                'cache_hit' => false,
                'stale' => false,
            ];
        } catch (ScreenerProviderException $exception) {
            $stale = Cache::get($staleKey);

            if (is_array($stale)) {
                return [
                    ...$stale,
                    'cache_hit' => true,
                    'stale' => true,
                ];
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $timeframeConfig
     */
    private function providerInterval(
        MarketDataProvider $provider,
        array $timeframeConfig
    ): string {
        $interval = match ($provider->name()) {
            'BINANCE', 'BINANCE_FUTURES' =>
                $timeframeConfig['binance_interval'] ?? null,
            'DERIV' =>
                $timeframeConfig['deriv_granularity'] ?? null,
            default =>
                $timeframeConfig['provider_interval'] ?? null,
        };

        $interval = trim((string) $interval);

        if ($interval === '') {
            throw new InvalidArgumentException(
                'This timeframe is not supported by the selected market provider.'
            );
        }

        return $interval;
    }

    private function providerOutputSize(
        MarketDataProvider $provider
    ): int {
        if (in_array($provider->name(), ['BINANCE', 'BINANCE_FUTURES'], true)) {
            return max(
                2,
                min(
                    1000,
                    (int) config(
                        'tradex_screener.providers.binance.output_size',
                        260
                    )
                )
            );
        }

        if ($provider->name() === 'DERIV') {
            return max(
                2,
                min(
                    5000,
                    (int) config(
                        'tradex_screener.providers.deriv.output_size',
                        260
                    )
                )
            );
        }

        if ($provider->name() === 'TWELVE_DATA_STOCKS') {
            return max(
                2,
                min(
                    5000,
                    (int) config(
                        'tradex_screener_stocks.provider.output_size',
                        260
                    )
                )
            );
        }

        return max(
            2,
            min(
                5000,
                (int) config(
                    'tradex_screener.providers.twelve_data.output_size',
                    260
                )
            )
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function applyFilters(
        array $rows,
        array $filters
    ): array {
        $symbols = array_values(array_filter(
            array_map(
                static fn (mixed $symbol): string =>
                    strtoupper(trim((string) $symbol)),
                (array) ($filters['symbols'] ?? [])
            ),
            static fn (string $symbol): bool =>
                $symbol !== ''
        ));

        $trend = strtoupper(
            trim((string) ($filters['trend'] ?? ''))
        );

        $alignment = strtoupper(
            trim((string) ($filters['ema_alignment'] ?? ''))
        );

        $rsiMin = isset($filters['rsi_min'])
            ? (float) $filters['rsi_min']
            : null;

        $rsiMax = isset($filters['rsi_max'])
            ? (float) $filters['rsi_max']
            : null;

        return array_values(array_filter(
            $rows,
            static function (array $row) use (
                $symbols,
                $trend,
                $alignment,
                $rsiMin,
                $rsiMax
            ): bool {
                if (
                    $symbols !== []
                    && ! in_array(
                        strtoupper((string) $row['symbol']),
                        $symbols,
                        true
                    )
                ) {
                    return false;
                }

                if (
                    $trend !== ''
                    && strtoupper((string) $row['trend'])
                        !== $trend
                ) {
                    return false;
                }

                if (
                    $alignment !== ''
                    && strtoupper((string) $row['ema_alignment'])
                        !== $alignment
                ) {
                    return false;
                }

                $rsi = $row['rsi_14'] ?? null;

                if (
                    $rsiMin !== null
                    && ($rsi === null || (float) $rsi < $rsiMin)
                ) {
                    return false;
                }

                if (
                    $rsiMax !== null
                    && ($rsi === null || (float) $rsi > $rsiMax)
                ) {
                    return false;
                }

                return true;
            }
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(
        array $rows,
        string $sort,
        string $order
    ): array {
        $sort = strtolower(trim($sort));
        $order = strtolower(trim($order)) === 'desc'
            ? 'desc'
            : 'asc';

        $field = match ($sort) {
            'change_percent' => 'change_percent',
            'rsi' => 'rsi_14',
            'atr_percent' => 'atr_percent',
            'trend' => 'trend',
            default => 'symbol',
        };

        usort(
            $rows,
            static function (
                array $left,
                array $right
            ) use (
                $field,
                $order
            ): int {
                $leftValue = $left[$field] ?? null;
                $rightValue = $right[$field] ?? null;

                if ($leftValue === $rightValue) {
                    return 0;
                }

                if ($leftValue === null) {
                    return 1;
                }

                if ($rightValue === null) {
                    return -1;
                }

                $comparison = is_numeric($leftValue)
                    && is_numeric($rightValue)
                        ? ((float) $leftValue <=> (float) $rightValue)
                        : strcasecmp(
                            (string) $leftValue,
                            (string) $rightValue
                        );

                return $order === 'desc'
                    ? -$comparison
                    : $comparison;
            }
        );

        return $rows;
    }

    private function volatilityLabel(mixed $atrPercent): ?string
    {
        if ($atrPercent === null) {
            return null;
        }

        $atrPercent = (float) $atrPercent;

        $lowMax = (float) config(
            'tradex_screener.indicators.volatility.low_max_percent',
            0.35
        );

        $mediumMax = (float) config(
            'tradex_screener.indicators.volatility.medium_max_percent',
            0.80
        );

        if ($atrPercent <= $lowMax) {
            return 'LOW';
        }

        if ($atrPercent <= $mediumMax) {
            return 'MEDIUM';
        }

        return 'HIGH';
    }
}
