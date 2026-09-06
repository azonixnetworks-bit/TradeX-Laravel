<?php

namespace Tests\Feature\Api\Screener;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ScreenerApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set(
            'tradex_screener.providers.twelve_data.enabled',
            true
        );

        config()->set(
            'tradex_screener.providers.twelve_data.api_key',
            'test-twelve-data-key'
        );

        config()->set(
            'tradex_screener.forex.symbols',
            [
                [
                    'symbol' => 'EURUSD',
                    'provider_symbol' => 'EUR/USD',
                    'name' => 'Euro / US Dollar',
                    'base_currency' => 'EUR',
                    'quote_currency' => 'USD',
                    'enabled' => true,
                ],
            ]
        );
    }

    public function test_screener_requires_mobile_authentication(): void
    {
        $this->getJson(
            '/api/v1/screener/meta'
        )->assertUnauthorized();
    }

    public function test_mobile_user_can_read_screener_meta_without_provider_call(): void
    {
        Http::fake();

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/meta')
            ->assertOk()
            ->assertJsonPath(
                'data.phase',
                'SCREENER-01'
            )
            ->assertJsonPath(
                'data.markets.0.key',
                'FOREX'
            )
            ->assertJsonPath(
                'data.markets.0.status',
                'ACTIVE'
            );

        Http::assertNothingSent();
    }

    public function test_forex_results_are_calculated_internally_and_cached(): void
    {
        Http::fake([
            'https://api.twelvedata.com/*' =>
                Http::response(
                    $this->timeSeriesPayload(),
                    200
                ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $first = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=FOREX&timeframe=H1&trend=BULLISH'
            );

        $first
            ->assertOk()
            ->assertJsonPath(
                'data.market',
                'FOREX'
            )
            ->assertJsonPath(
                'data.timeframe',
                'H1'
            )
            ->assertJsonPath(
                'data.results.0.symbol',
                'EURUSD'
            )
            ->assertJsonPath(
                'data.results.0.trend',
                'BULLISH'
            )
            ->assertJsonPath(
                'data.results.0.ema_alignment',
                'BULLISH'
            )
            ->assertJsonPath(
                'data.cache.hit',
                false
            )
            ->assertJsonPath(
                'data.cache.stale',
                false
            );

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=FOREX&timeframe=H1'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.cache.hit',
                true
            );

        Http::assertSentCount(1);

        Http::assertSent(
            static fn ($request): bool =>
                $request['timezone'] === 'UTC'
                && $request['interval'] === '1h'
                && $request['symbol'] === 'EUR/USD'
        );
    }

    public function test_stale_dataset_is_served_when_provider_temporarily_fails(): void
    {
        Http::fakeSequence()
            ->push(
                $this->timeSeriesPayload(),
                200
            )
            ->push(
                ['message' => 'temporary failure'],
                500
            );

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=FOREX&timeframe=H1'
            )
            ->assertOk();

        Cache::forget(
            'tradex:screener:fresh:v1:FOREX:H1'
        );

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=FOREX&timeframe=H1'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.cache.stale',
                true
            );

        Http::assertSentCount(2);
    }

    public function test_provider_failure_without_stale_cache_returns_safe_503(): void
    {
        Http::fake([
            '*' => Http::response(
                ['message' => 'upstream failure'],
                500
            ),
        ]);

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $response = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=FOREX&timeframe=H1'
            );

        $response
            ->assertStatus(503)
            ->assertJsonPath(
                'success',
                false
            )
            ->assertJsonPath(
                'error.code',
                'SCREENER_PROVIDER_HTTP_ERROR'
            );

        $this->assertStringNotContainsString(
            'test-twelve-data-key',
            $response->getContent()
        );
    }

    public function test_phase_one_rejects_non_forex_results_but_exposes_roadmap_market(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=CRYPTO&timeframe=H1'
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(
                'market'
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function timeSeriesPayload(): array
    {
        $values = [];

        for ($index = 0; $index < 260; $index++) {
            $close = 1.0500 + ($index * 0.0002);

            $values[] = [
                'datetime' =>
                    now('UTC')
                        ->subHours(
                            259 - $index
                        )
                        ->format(
                            'Y-m-d H:i:s'
                        ),
                'open' => number_format(
                    $close - 0.00005,
                    5,
                    '.',
                    ''
                ),
                'high' => number_format(
                    $close + 0.00010,
                    5,
                    '.',
                    ''
                ),
                'low' => number_format(
                    $close - 0.00010,
                    5,
                    '.',
                    ''
                ),
                'close' => number_format(
                    $close,
                    5,
                    '.',
                    ''
                ),
            ];
        }

        return [
            'meta' => [
                'symbol' => 'EUR/USD',
                'interval' => '1h',
                'currency_base' => 'Euro',
                'currency_quote' => 'US Dollar',
                'type' => 'Physical Currency',
            ],
            'values' => $values,
            'status' => 'ok',
        ];
    }
}
