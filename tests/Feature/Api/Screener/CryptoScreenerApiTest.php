<?php

namespace Tests\Feature\Api\Screener;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CryptoScreenerApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set(
            'tradex_screener.providers.binance.enabled',
            true
        );

        config()->set(
            'tradex_screener.providers.binance.base_url',
            'https://data-api.binance.vision'
        );

        config()->set(
            'tradex_screener.crypto.symbols',
            [
                [
                    'symbol' => 'BTCUSDT',
                    'provider_symbol' => 'BTCUSDT',
                    'name' => 'Bitcoin / Tether',
                    'base_currency' => 'BTC',
                    'quote_currency' => 'USDT',
                    'enabled' => true,
                ],
            ]
        );
    }

    public function test_crypto_results_use_binance_public_klines_and_cache(): void
    {
        Http::fake([
            'https://data-api.binance.vision/*' => Http::response(
                $this->klinePayload(),
                200
            ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $first = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=CRYPTO&timeframe=H1&trend=BULLISH'
            );

        $first
            ->assertOk()
            ->assertJsonPath('data.market', 'CRYPTO')
            ->assertJsonPath('data.provider', 'BINANCE')
            ->assertJsonPath('data.timeframe', 'H1')
            ->assertJsonPath('data.results.0.symbol', 'BTCUSDT')
            ->assertJsonPath('data.results.0.trend', 'BULLISH')
            ->assertJsonPath('data.results.0.ema_alignment', 'BULLISH')
            ->assertJsonPath('data.cache.hit', false)
            ->assertJsonPath('data.cache.stale', false);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=CRYPTO&timeframe=H1'
            )
            ->assertOk()
            ->assertJsonPath('data.cache.hit', true);

        Http::assertSentCount(1);

        Http::assertSent(
            static fn ($request): bool => $request['symbol'] === 'BTCUSDT'
                && $request['interval'] === '1h'
                && (int) $request['limit'] === 260
        );
    }

    public function test_crypto_symbol_detail_validates_against_crypto_registry(): void
    {
        Http::fake([
            'https://data-api.binance.vision/*' => Http::response(
                $this->klinePayload(),
                200
            ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/symbols/BTCUSDT?market=CRYPTO&timeframe=H4'
            )
            ->assertOk()
            ->assertJsonPath('data.market', 'CRYPTO')
            ->assertJsonPath('data.provider', 'BINANCE')
            ->assertJsonPath('data.symbol.symbol', 'BTCUSDT')
            ->assertJsonPath('data.symbol.base_currency', 'BTC')
            ->assertJsonPath('data.symbol.quote_currency', 'USDT');

        Http::assertSent(
            static fn ($request): bool => $request['interval'] === '4h'
        );
    }

    public function test_crypto_bubbles_are_available_to_mobile_users(): void
    {
        Http::fake([
            'https://data-api.binance.vision/api/v3/ticker/24hr*' => Http::response([[
                'symbol' => 'BTCUSDT',
                'lastPrice' => '67842.20',
                'volume' => '32500',
                'quoteVolume' => '2200000000',
                'openPrice' => '66199.00',
                'highPrice' => '68100.00',
                'lowPrice' => '65500.00',
                'weightedAvgPrice' => '67100.00',
                'count' => 1250000,
            ]], 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=50')
            ->assertOk()
            ->assertJsonPath('data.market', 'CRYPTO')
            ->assertJsonPath('data.provider', 'BINANCE')
            ->assertJsonPath('data.assets.0.symbol', 'BTCUSDT')
            ->assertJsonPath('data.assets.0.price', 67842.2)
            ->assertJsonPath('data.summary.gainers', 1)
            ->assertJsonPath('data.summary.total_quote_volume', 2200000000);

        Http::assertSentCount(1);
        Http::assertSent(
            static fn ($request): bool => $request['type'] === 'MINI'
        );
    }

    public function test_crypto_bubble_limit_is_bounded(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }

    public function test_paginated_crypto_bubbles_rank_live_top_gainers(): void
    {
        Http::fake([
            'https://data-api.binance.vision/api/v3/ticker/24hr*' => Http::response([
                $this->ticker('BTCUSDT', 101, 100, 3000000),
                $this->ticker('ETHUSDT', 108, 100, 2000000),
                $this->ticker('XRPUSDT', 104, 100, 1000000),
                $this->ticker('BNBUSDT', 95, 100, 4000000),
            ], 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=100&page=1&per_page=20')
            ->assertOk()
            ->assertJsonPath('data.bubble_selection', 'TOP_GAINERS_24H')
            ->assertJsonPath('data.bubbles.0.symbol', 'ETHUSDT')
            ->assertJsonPath('data.bubbles.1.symbol', 'XRPUSDT')
            ->assertJsonPath('data.bubbles.2.symbol', 'BTCUSDT')
            ->assertJsonPath('data.bubbles.3.symbol', 'BNBUSDT')
            ->assertJsonCount(4, 'data.bubbles');
    }

    public function test_paginated_crypto_bubbles_rank_live_top_losers(): void
    {
        Http::fake([
            'https://data-api.binance.vision/api/v3/ticker/24hr*' => Http::response([
                $this->ticker('BTCUSDT', 101, 100, 3000000),
                $this->ticker('ETHUSDT', 108, 100, 2000000),
                $this->ticker('XRPUSDT', 96, 100, 1000000),
                $this->ticker('BNBUSDT', 91, 100, 4000000),
            ], 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=100&page=1&per_page=20&bubble_mode=LOSERS')
            ->assertOk()
            ->assertJsonPath('data.bubble_selection', 'TOP_LOSERS_24H')
            ->assertJsonPath('data.bubbles.0.symbol', 'BNBUSDT')
            ->assertJsonPath('data.bubbles.1.symbol', 'XRPUSDT')
            ->assertJsonPath('data.bubbles.2.symbol', 'BTCUSDT')
            ->assertJsonPath('data.bubbles.3.symbol', 'ETHUSDT')
            ->assertJsonCount(4, 'data.bubbles');
    }

    public function test_crypto_bubble_mode_is_validated(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=50&bubble_mode=UNKNOWN')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bubble_mode');
    }

    public function test_paginated_crypto_bubbles_are_capped_at_top_twenty(): void
    {
        $tickers = [];
        for ($index = 0; $index < 25; $index++) {
            $change = $index < 4 ? 4 - $index : -$index;
            $tickers[] = $this->ticker(
                'COIN'.str_pad((string) $index, 2, '0', STR_PAD_LEFT).'USDT',
                100 + $change,
                100,
                25000000 - $index
            );
        }

        Http::fake([
            'https://data-api.binance.vision/api/v3/ticker/24hr*' => Http::response($tickers, 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=100&page=1&per_page=20')
            ->assertOk()
            ->assertJsonCount(20, 'data.bubbles')
            ->assertJsonPath('data.bubbles.0.symbol', 'COIN00USDT')
            ->assertJsonPath('data.bubbles.19.symbol', 'COIN19USDT');
    }

    public function test_crypto_symbols_can_be_ranked_by_live_spot_volume(): void
    {
        Http::fake([
            'https://data-api.binance.vision/api/v3/exchangeInfo*' => Http::response([
                'symbols' => [
                    $this->spotSymbol('BTCUSDT', 'BTC'),
                    $this->spotSymbol('ETHUSDT', 'ETH'),
                ],
            ], 200),
            'https://data-api.binance.vision/api/v3/ticker/24hr*' => Http::response([
                $this->ticker('BTCUSDT', 101, 100, 1000000),
                $this->ticker('ETHUSDT', 108, 100, 9000000),
            ], 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/symbols?market=CRYPTO&segment=SPOT&sort=VOLUME&page=1&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.segment', 'SPOT')
            ->assertJsonPath('data.symbols.0.symbol', 'ETHUSDT')
            ->assertJsonPath('data.symbols.0.quote_volume', 9000000)
            ->assertJsonPath('data.symbols.1.symbol', 'BTCUSDT')
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_crypto_bubbles_support_futures_market_data(): void
    {
        Http::fake([
            'https://fapi.binance.com/fapi/v1/ticker/24hr*' => Http::response([
                $this->ticker('BTCUSDT', 105, 100, 8000000),
            ], 200),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/bubbles?limit=50&segment=FUTURES')
            ->assertOk()
            ->assertJsonPath('data.segment', 'FUTURES')
            ->assertJsonPath('data.assets.0.symbol', 'BTCUSDT')
            ->assertJsonPath('data.assets.0.name', 'BTC / USDT Perpetual');
    }

    public function test_binance_rate_limit_returns_safe_503_without_stale_cache(): void
    {
        Http::fake([
            'https://data-api.binance.vision/*' => Http::response(
                ['code' => -1003, 'msg' => 'Too many requests'],
                429
            ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=CRYPTO&timeframe=M15'
            )
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'error.code',
                'SCREENER_PROVIDER_RATE_LIMITED'
            );
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    private function klinePayload(): array
    {
        $rows = [];

        for ($index = 0; $index < 260; $index++) {
            $close = 50000 + ($index * 25);
            $openTime = now('UTC')
                ->subHours(259 - $index)
                ->getTimestamp() * 1000;

            $rows[] = [
                $openTime,
                number_format($close - 10, 2, '.', ''),
                number_format($close + 20, 2, '.', ''),
                number_format($close - 20, 2, '.', ''),
                number_format($close, 2, '.', ''),
                number_format(100 + $index, 4, '.', ''),
                $openTime + 3599999,
                '0',
                1000 + $index,
                '0',
                '0',
                '0',
            ];
        }

        return $rows;
    }

    /** @return array<string,int|string> */
    private function ticker(
        string $symbol,
        float $lastPrice,
        float $openPrice,
        float $quoteVolume
    ): array {
        return [
            'symbol' => $symbol,
            'lastPrice' => (string) $lastPrice,
            'openPrice' => (string) $openPrice,
            'volume' => '1000',
            'quoteVolume' => (string) $quoteVolume,
            'highPrice' => (string) max($lastPrice, $openPrice),
            'lowPrice' => (string) min($lastPrice, $openPrice),
            'weightedAvgPrice' => (string) $lastPrice,
            'count' => 100,
        ];
    }

    /** @return array<string,bool|string> */
    private function spotSymbol(string $symbol, string $base): array
    {
        return [
            'symbol' => $symbol,
            'baseAsset' => $base,
            'quoteAsset' => 'USDT',
            'status' => 'TRADING',
            'isSpotTradingAllowed' => true,
        ];
    }
}
