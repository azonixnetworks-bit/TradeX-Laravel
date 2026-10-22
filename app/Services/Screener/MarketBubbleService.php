<?php

namespace App\Services\Screener;

use App\Exceptions\ScreenerProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MarketBubbleService
{
    private const CACHE_KEY = 'tradex:screener:market-bubbles:crypto:24h:v2';

    private const STALE_CACHE_KEY = 'tradex:screener:market-bubbles:crypto:24h:stale:v2';

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
    public function crypto24h(int $limit = 50, string $segment = 'SPOT'): array
    {
        $limit = max(20, min(300, $limit));
        $segment = strtoupper($segment) === 'FUTURES' ? 'FUTURES' : 'SPOT';

        $cacheKey = self::CACHE_KEY.':'.strtolower($segment).':'.$limit;
        $staleCacheKey = self::STALE_CACHE_KEY.':'.strtolower($segment).':'.$limit;
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && isset($cached['assets'])) {
            return [
                ...$cached,
                'source' => 'BINANCE_CACHE',
                'stale' => false,
            ];
        }

        try {
            $request = Http::acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout());
            $response = $segment === 'FUTURES'
                ? $request->get($this->futuresBaseUrl().'/fapi/v1/ticker/24hr')
                : $request->get(
                    $this->baseUrl().'/api/v3/ticker/24hr',
                    ['type' => 'MINI']
                );

            $payload = $response->json();

            if (in_array($response->status(), [418, 429], true)) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_RATE_LIMITED',
                    'Binance bubble data is temporarily rate limited.'
                );
            }

            if (! $response->successful() || ! is_array($payload)) {
                throw new ScreenerProviderException(
                    'SCREENER_PROVIDER_HTTP_ERROR',
                    'Binance bubble data is temporarily unavailable.'
                );
            }

            $resolved = $this->normalise($payload, $limit, $segment);

            Cache::put($cacheKey, $resolved, now()->addSeconds(10));
            Cache::put($staleCacheKey, $resolved, now()->addMinutes(10));

            return [
                ...$resolved,
                'source' => 'BINANCE_LIVE',
                'stale' => false,
            ];
        } catch (ConnectionException|ScreenerProviderException) {
            $stale = Cache::get($staleCacheKey);

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
    public function normalise(
        array $tickers,
        int $limit = 50,
        string $segment = 'SPOT'
    ): array
    {
        $segment = strtoupper($segment) === 'FUTURES' ? 'FUTURES' : 'SPOT';
        $assets = [];

        foreach ($tickers as $ticker) {
            if (! is_array($ticker)) {
                continue;
            }

            $symbol = strtoupper(trim((string) ($ticker['symbol'] ?? '')));
            if (! str_ends_with($symbol, 'USDT')) {
                continue;
            }

            $base = substr($symbol, 0, -4);
            if ($base === '') {
                continue;
            }

            $price = (float) ($ticker['lastPrice'] ?? 0);
            $open = (float) ($ticker['openPrice'] ?? 0);
            $changeAmount = array_key_exists('priceChange', $ticker)
                ? (float) $ticker['priceChange']
                : $price - $open;
            $change = array_key_exists('priceChangePercent', $ticker)
                ? (float) $ticker['priceChangePercent']
                : ($open > 0 ? ($changeAmount / $open) * 100 : 0.0);
            $volume = (float) ($ticker['volume'] ?? 0);
            $quoteVolume = (float) ($ticker['quoteVolume'] ?? 0);

            if ($price <= 0 || $quoteVolume <= 0) {
                continue;
            }

            $assets[] = [
                'id' => $symbol,
                'symbol' => $symbol,
                'name' => $base.' / USDT'.($segment === 'FUTURES' ? ' Perpetual' : ''),
                'base_currency' => $base,
                'quote_currency' => 'USDT',
                'segment' => $segment,
                'logo_url' => 'https://cdn.jsdelivr.net/gh/spothq/cryptocurrency-icons@master/128/color/'.strtolower($base).'.png',
                'price' => $price,
                'change_percent' => $change,
                'change_amount' => $changeAmount,
                'volume' => $volume,
                'quote_volume' => $quoteVolume,
                'open' => $open,
                'high' => (float) ($ticker['highPrice'] ?? 0),
                'low' => (float) ($ticker['lowPrice'] ?? 0),
                'weighted_average_price' => array_key_exists('weightedAvgPrice', $ticker)
                    ? (float) $ticker['weightedAvgPrice']
                    : ($volume > 0 ? $quoteVolume / $volume : 0.0),
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
            'segment' => $segment,
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

    private function futuresBaseUrl(): string
    {
        return rtrim(
            (string) config(
                'binance_futures.live_catalog.base_url',
                'https://fapi.binance.com'
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
