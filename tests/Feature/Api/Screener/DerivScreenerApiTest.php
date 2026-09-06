<?php

namespace Tests\Feature\Api\Screener;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Exceptions\ScreenerProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class DerivScreenerApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set(
            'tradex_screener.providers.deriv.enabled',
            true
        );
        config()->set(
            'tradex_screener.providers.deriv.output_size',
            260
        );
        config()->set(
            'tradex_screener.deriv.symbols',
            [
                [
                    'symbol' => '1HZ100V',
                    'provider_symbol' => '1HZ100V',
                    'name' => 'Volatility 100 (1s) Index',
                    'base_currency' => '',
                    'quote_currency' => '',
                    'enabled' => true,
                ],
            ]
        );
    }

    public function test_deriv_results_use_public_candle_history_and_internal_indicators(): void
    {
        $transport = $this->bindTransport();
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $response = $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=DERIV&timeframe=H1&symbols=1HZ100V'
            );

        $response
            ->assertOk()
            ->assertJsonPath('data.market', 'DERIV')
            ->assertJsonPath('data.provider', 'DERIV')
            ->assertJsonPath('data.results.0.symbol', '1HZ100V')
            ->assertJsonPath('data.results.0.provider_symbol', '1HZ100V')
            ->assertJsonPath('data.results.0.trend', 'BULLISH')
            ->assertJsonPath('data.results.0.ema_alignment', 'BULLISH')
            ->assertJsonPath('data.results.0.volume', null)
            ->assertJsonPath('data.cache.hit', false)
            ->assertJsonPath('data.cache.stale', false);

        $this->assertCount(1, $transport->batches);
        $request = $transport->batches[0][0];

        $this->assertSame('1HZ100V', $request['ticks_history']);
        $this->assertSame('latest', $request['end']);
        $this->assertSame('candles', $request['style']);
        $this->assertSame(3600, $request['granularity']);
        $this->assertSame(260, $request['count']);
        $this->assertSame(0, $request['subscribe']);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=DERIV&timeframe=H1&symbols=1HZ100V'
            )
            ->assertOk()
            ->assertJsonPath('data.cache.hit', true);

        $this->assertCount(1, $transport->batches);
    }

    public function test_deriv_stale_cache_is_used_when_websocket_temporarily_fails(): void
    {
        $transport = $this->bindTransport();
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=DERIV&timeframe=H1'
            )
            ->assertOk();

        Cache::forget(
            'tradex:screener:fresh:v1:DERIV:H1'
        );
        $transport->fail = true;

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=DERIV&timeframe=H1'
            )
            ->assertOk()
            ->assertJsonPath('data.cache.hit', true)
            ->assertJsonPath('data.cache.stale', true);
    }

    public function test_deriv_failure_without_stale_cache_returns_safe_503(): void
    {
        $transport = $this->bindTransport();
        $transport->fail = true;

        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/results?market=DERIV&timeframe=H1'
            )
            ->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath(
                'error.code',
                'SCREENER_PROVIDER_TIMEOUT'
            );
    }

    public function test_deriv_symbol_detail_supports_provider_symbol_with_digits(): void
    {
        $this->bindTransport();
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson(
                '/api/v1/screener/symbols/1HZ100V?market=DERIV&timeframe=H1'
            )
            ->assertOk()
            ->assertJsonPath('data.market', 'DERIV')
            ->assertJsonPath('data.symbol.symbol', '1HZ100V');
    }

    private function bindTransport(): object
    {
        $candles = $this->candles();

        $transport = new class($candles) implements DerivWebSocketTransport
        {
            /** @var array<int, array<int, array<string, mixed>>> */
            public array $batches = [];

            public bool $fail = false;

            /** @param array<int, array<string, mixed>> $candles */
            public function __construct(
                private readonly array $candles
            ) {
            }

            public function requestMany(array $requests): array
            {
                $this->batches[] = $requests;

                if ($this->fail) {
                    throw new ScreenerProviderException(
                        'SCREENER_PROVIDER_TIMEOUT',
                        'Deriv market data request timed out.'
                    );
                }

                return array_map(
                    fn (array $request): array => [
                        'req_id' => (int) ($request['req_id'] ?? 0),
                        'msg_type' => 'candles',
                        'candles' => $this->candles,
                    ],
                    $requests
                );
            }

            public function endpointHost(): ?string
            {
                return 'api.derivws.com';
            }
        };

        $this->app->instance(
            DerivWebSocketTransport::class,
            $transport
        );

        return $transport;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candles(): array
    {
        $values = [];
        $start = now('UTC')
            ->subHours(259)
            ->startOfHour();

        for ($index = 0; $index < 260; $index++) {
            $close = 1000 + ($index * 2);

            $values[] = [
                'epoch' => $start
                    ->copy()
                    ->addHours($index)
                    ->timestamp,
                'open' => $close - 1,
                'high' => $close + 2,
                'low' => $close - 2,
                'close' => $close,
            ];
        }

        return $values;
    }
}
