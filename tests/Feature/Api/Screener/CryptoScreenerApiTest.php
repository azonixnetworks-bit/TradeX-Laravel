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
            static fn ($request): bool =>
                $request['symbol'] === 'BTCUSDT'
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
            static fn ($request): bool =>
                $request['interval'] === '4h'
        );
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
}
