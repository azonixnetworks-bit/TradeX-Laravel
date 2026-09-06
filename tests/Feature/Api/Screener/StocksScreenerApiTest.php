<?php

namespace Tests\Feature\Api\Screener;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class StocksScreenerApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('tradex_screener_stocks.enabled', true);
        config()->set('tradex_screener_stocks.provider.enabled', true);
        config()->set(
            'tradex_screener_stocks.provider.external_display_approved',
            true
        );
        config()->set(
            'tradex_screener_stocks.provider.api_key',
            'test-stocks-key'
        );
        config()->set(
            'tradex_screener_stocks.symbols',
            $this->stockSymbols()
        );
    }

    public function test_stock_symbols_can_be_filtered_by_region_without_provider_call(): void
    {
        Http::fake();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/symbols?market=STOCKS&region=INTERNATIONAL'
            )
            ->assertOk()
            ->assertJsonPath('data.market', 'STOCKS')
            ->assertJsonPath('data.region', 'INTERNATIONAL')
            ->assertJsonCount(1, 'data.symbols')
            ->assertJsonPath('data.symbols.0.symbol', 'AAPL');

        Http::assertNothingSent();
    }

    public function test_us_stock_intraday_results_use_mic_and_utc(): void
    {
        Http::fake([
            'https://api.twelvedata.com/*' => Http::response(
                $this->stockPayload(
                    'AAPL',
                    'America/New_York',
                    '1h',
                    true
                ),
                200
            ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $response = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=STOCKS&region=INTERNATIONAL&exchange=NASDAQ&timeframe=H1'
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.market', 'STOCKS')
            ->assertJsonPath('data.provider', 'TWELVE_DATA_STOCKS')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.results.0.symbol', 'AAPL')
            ->assertJsonPath('data.results.0.region', 'INTERNATIONAL')
            ->assertJsonPath('data.results.0.exchange', 'NASDAQ')
            ->assertJsonPath('data.results.0.mic_code', 'XNAS')
            ->assertJsonPath('data.results.0.currency', 'USD')
            ->assertJsonPath('data.results.0.data_mode', 'REALTIME');

        Http::assertSentCount(1);
        Http::assertSent(
            static fn ($request): bool =>
                $request['symbol'] === 'AAPL'
                && $request['mic_code'] === 'XNAS'
                && $request['interval'] === '1h'
                && $request['timezone'] === 'UTC'
        );

        $this->assertStringNotContainsString(
            'test-stocks-key',
            $response->getContent()
        );
    }

    public function test_india_region_is_eod_only(): void
    {
        Http::fake();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=STOCKS&region=INDIA&timeframe=H1'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('timeframe');

        Http::assertNothingSent();
    }

    public function test_india_daily_result_is_normalised_from_exchange_timezone_to_utc(): void
    {
        Http::fake(function ($request) {
            $symbol = (string) $request['symbol'];

            return Http::response(
                $this->stockPayload(
                    $symbol,
                    $symbol === 'RELIANCE'
                        ? 'Asia/Kolkata'
                        : 'America/New_York',
                    '1day',
                    false
                ),
                200
            );
        });

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $response = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=STOCKS&region=INDIA&exchange=NSE&timeframe=D1'
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.results.0.symbol', 'RELIANCE')
            ->assertJsonPath('data.results.0.region', 'INDIA')
            ->assertJsonPath('data.results.0.exchange', 'NSE')
            ->assertJsonPath('data.results.0.data_mode', 'EOD');

        $candleTime = (string) $response->json(
            'data.results.0.candle_time_utc'
        );

        $this->assertStringEndsWith('+00:00', $candleTime);

        Http::assertSent(
            static fn ($request): bool =>
                $request['symbol'] === 'RELIANCE'
                && $request['mic_code'] === 'XNSE'
                && $request['interval'] === '1day'
        );
    }

    public function test_sri_lanka_region_stays_blocked_until_approved_cse_provider_exists(): void
    {
        Http::fake();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=STOCKS&region=SRI_LANKA&timeframe=D1'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('region');

        Http::assertNothingSent();
    }

    public function test_customer_stock_data_is_blocked_without_display_approval(): void
    {
        config()->set(
            'tradex_screener_stocks.provider.external_display_approved',
            false
        );

        Http::fake();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=STOCKS&region=INTERNATIONAL&timeframe=H1'
            )
            ->assertStatus(503)
            ->assertJsonPath(
                'error.code',
                'SCREENER_PROVIDER_NOT_CONFIGURED'
            );

        Http::assertNothingSent();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stockSymbols(): array
    {
        return [
            [
                'symbol' => 'AAPL',
                'provider_symbol' => 'AAPL',
                'name' => 'Apple Inc.',
                'region' => 'INTERNATIONAL',
                'exchange' => 'NASDAQ',
                'mic_code' => 'XNAS',
                'exchange_timezone' => 'America/New_York',
                'currency' => 'USD',
                'data_mode' => 'REALTIME',
                'supported_timeframes' => ['M5', 'M15', 'H1', 'H4', 'D1'],
                'enabled' => true,
            ],
            [
                'symbol' => 'RELIANCE',
                'provider_symbol' => 'RELIANCE',
                'name' => 'Reliance Industries Limited',
                'region' => 'INDIA',
                'exchange' => 'NSE',
                'mic_code' => 'XNSE',
                'exchange_timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'data_mode' => 'EOD',
                'supported_timeframes' => ['D1'],
                'enabled' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockPayload(
        string $symbol,
        string $exchangeTimezone,
        string $interval,
        bool $intraday
    ): array {
        $values = [];

        for ($index = 0; $index < 260; $index++) {
            $close = 100 + ($index * 0.25);
            $datetime = $intraday
                ? now('UTC')
                    ->subHours(259 - $index)
                    ->format('Y-m-d H:i:s')
                : now($exchangeTimezone)
                    ->subDays(259 - $index)
                    ->format('Y-m-d');

            $values[] = [
                'datetime' => $datetime,
                'open' => number_format($close - 0.10, 2, '.', ''),
                'high' => number_format($close + 0.20, 2, '.', ''),
                'low' => number_format($close - 0.20, 2, '.', ''),
                'close' => number_format($close, 2, '.', ''),
                'volume' => (string) (1000000 + ($index * 1000)),
            ];
        }

        return [
            'meta' => [
                'symbol' => $symbol,
                'interval' => $interval,
                'exchange_timezone' => $exchangeTimezone,
                'type' => 'Common Stock',
            ],
            'values' => $values,
            'status' => 'ok',
        ];
    }
}
