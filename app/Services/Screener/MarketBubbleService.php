<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MarketBubbleService
{
    private const CACHE_KEY = 'tradex:screener:market-bubbles:crypto:24h:v1';

    private const STALE_CACHE_KEY = 'tradex:screener:market-bubbles:crypto:24h:stale:v1';

    public function __construct(
        private readonly BinanceSpotCatalogService $catalog
    ) {
    }

    /**
     * @return array{
     *   market:string,
     *   timeframe:string,
     *   provider:string,
     *   generated_at_utc:string,
     *   source:string,
     *   stale:bool,
     *   summary:array<string,int|float>,
     *   assets:array<int,array<string,mixed>>
     * }
     */
    public function crypto24h(int $limit = 100): array
    {
        $limit = max(20, min(300, $limit));

        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['assets'])) {
            return [
                ...$cached,
                'source' => 'BINANCE_CACHE',
                'stale' => false,
            ];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get($this->baseUrl().'/api/v3/ticker/24hr');

            if (in_array($response->status(), [418, 429], true)) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_RATE_LIMITED',
                    'Binance bubble data is temporarily rate limited.'
                );
            }

            if (! $response->successful() || ! is_array($response->json())) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'Binance bubble data is temporarily unavailable.'
                );
            }

            $resolved = $this->normalise((array) $response->json(), $limit);

            Cache::put(self::CACHE_KEY, $resolved, now()->addSeconds(10));
            Cache::put(self::STALE_CACHE_KEY, $resolved, now()->addMinutes(10));

            return [
                ...$resolved,
                'source' => 'BINANCE_LIVE',
                'stale' => false,
            ];
        } catch (ConnectionException|ScreenerProviderException) {
            $stale = Cache::get(self::STALE_CACHE_KEY);

            if (is_array($stale) && isset($stale['assets'])) {
                return [
                    ...$stale,
                    'source' => 'BINANCE_STALE_CACHE',
                    'stale' => true,
                ];
            }

            throw new ScreenerProviderException(
                'SCREENER_PROVIDER_UNAVAILABLE',
                'Crypto bubble data is temporarily unavailable.'
            );
        }
    }

    /**
     * @param array<int,mixed> $tickers
     * @return array<string,mixed>
     */
    public function normalise(array $tickers, int $limit = 100): array
    {
        $catalog = $this->catalog->catalog();

        $symbols = collect((array) ($catalog['symbols'] ?? []))
            ->keyBy(static fn (array $item): string => strtoupper((string) ($item['symbol'] ?? '')));

        $assets = [];

        foreach ($tickers as $ticker) {
            if (! is_array($ticker)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($ticker['symbol'] ?? '')));
            $config = $symbols->get($symbol);

            if (! is_array($config)) {
                continue;
            }

            $price = (float) ($ticker['lastPrice'] ?? 0);
            $change = (float) ($ticker['priceChangePercent'] ?? 0);
            $quoteVolume = (float) ($ticker['quoteVolume'] ?? 0);

            if ($price <= 0 || $quoteVolume <= 0) {
                continue;
            }

            $assets[] = [
                'id' => $symbol,
                'symbol' => $symbol,
                'name' => (string) ($config['name'] ?? $symbol),
                'base_currency' => (string) ($config['base_currency'] ?? ''),
                'quote_currency' => (string) ($config['quote_currency'] ?? 'USDT'),
                'logo_url' => $config['logo_url'] ?? null,
                'price' => $price,
                'change_percent' => $change,
                'change_amount' => (float) ($ticker['priceChange'] ?? 0),
                'volume' => (float) ($ticker['volume'] ?? 0),
                'quote_volume' => $quoteVolume,
                'open' => (float) ($ticker['openPrice'] ?? 0),
                'high' => (float) ($ticker['highPrice'] ?? 0),
                'low' => (float) ($ticker['lowPrice'] ?? 0),
                'weighted_average_price' => (float) ($ticker['weightedAvgPrice'] ?? 0),
                'trade_count' => (int) ($ticker['count'] ?? 0),
            ];
        }

        usort(
            $assets,
            static fn (array $left, array $right): int =>
                ($right['quote_volume'] <=> $left['quote_volume'])
        );

        $assets = array_slice($assets, 0, max(20, min(300, $limit)));

        $gainers = count(array_filter(
            $assets,
            static fn (array $asset): bool => (float) $asset['change_percent'] > 0
        ));

        $losers = count(array_filter(
            $assets,
            static fn (array $asset): bool => (float) $asset['change_percent'] < 0
        ));

        $totalChange = array_sum(array_map(
            static fn (array $asset): float => (float) $asset['change_percent'],
            $assets
        ));

        return [
            'market' => 'CRYPTO',
            'timeframe' => '24H',
            'provider' => 'BINANCE',
            'generated_at_utc' => now('UTC')->toIso8601String(),
            'summary' => [
                'total_assets' => count($assets),
                'gainers' => $gainers,
                'losers' => $losers,
                'unchanged' => max(0, count($assets) - $gainers - $losers),
                'average_change' => count($assets) > 0
                    ? round($totalChange / count($assets), 2)
                    : 0.0,
                'total_quote_volume' => round(array_sum(array_column($assets, 'quote_volume')), 2),
            ],
            'assets' => $assets,
        ];
    }

    private function baseUrl(): string
    {
        return rtrim(
            (string) config(
                'tradex_screener.providers.binance.base_url',
                'https://data-api.binance.vision'
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
                    'tradex_screener.providers.binance.timeout_seconds',
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
                $this->timeout(),
                (int) config(
                    'tradex_screener.providers.binance.connect_timeout_seconds',
                    5
                )
            )
        );
    }
}
