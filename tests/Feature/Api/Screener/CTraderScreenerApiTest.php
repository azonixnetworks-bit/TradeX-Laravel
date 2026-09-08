<?php

namespace Tests\Feature\Api\Screener;

use App\Contracts\Screener\CTraderJsonTransport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CTraderScreenerApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('tradex_screener.enabled', true);
        config()->set('tradex_screener_ctrader.enabled', true);
        config()->set('tradex_screener_ctrader.environment', 'DEMO');
        config()->set('tradex_screener_ctrader.client_id', 'client-id');
        config()->set('tradex_screener_ctrader.client_secret', 'client-secret');
        config()->set('tradex_screener_ctrader.access_token', 'access-token');
        config()->set('tradex_screener_ctrader.ctid_trader_account_id', '123456789');
        config()->set('tradex_screener_ctrader.fallback_to_twelve_data', false);
        config()->set('tradex_screener_ctrader.output_size', 260);
        config()->set('tradex_screener.forex.symbols', [
            [
                'symbol' => 'EURUSD',
                'provider_symbol' => 'EUR/USD',
                'name' => 'Euro / US Dollar',
                'base_currency' => 'EUR',
                'quote_currency' => 'USD',
                'enabled' => true,
            ],
        ]);

        $this->app->instance(
            CTraderJsonTransport::class,
            $this->fakeTransport()
        );
    }

    public function test_mobile_screener_uses_ctrader_forex_trendbars(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);

        $this->withToken($token)
            ->getJson('/api/v1/screener/results?market=FOREX&timeframe=H1')
            ->assertOk()
            ->assertJsonPath('data.market', 'FOREX')
            ->assertJsonPath('data.provider', 'CTRADER_IC_MARKETS')
            ->assertJsonPath('data.results.0.symbol', 'EURUSD')
            ->assertJsonPath('data.results.0.trend', 'BULLISH')
            ->assertJsonPath('data.results.0.ema_alignment', 'BULLISH')
            ->assertJsonPath('data.cache.stale', false);
    }

    private function fakeTransport(): object
    {
        return new class implements CTraderJsonTransport
        {
            public function exchange(array $steps): array
            {
                $responses = [];

                foreach ($steps as $step) {
                    $request = $step['request'];
                    $type = (int) $request['payloadType'];
                    $payload = [];

                    if ($type === 2102) {
                        $payload = [
                            'ctidTraderAccountId' => 123456789,
                        ];
                    } elseif ($type === 2114) {
                        $payload = [
                            'ctidTraderAccountId' => 123456789,
                            'symbol' => [
                                [
                                    'symbolId' => 101,
                                    'symbolName' => 'EUR/USD',
                                    'enabled' => true,
                                ],
                            ],
                        ];
                    } elseif ($type === 2137) {
                        $payload = [
                            'ctidTraderAccountId' => 123456789,
                            'period' => 9,
                            'symbolId' => 101,
                            'trendbar' => $this->trendbars(),
                        ];
                    }

                    $responses[] = [
                        'clientMsgId' => $request['clientMsgId'],
                        'payloadType' => (int) $step['expect_payload_type'],
                        'payload' => $payload,
                    ];
                }

                return $responses;
            }

            public function endpointHost(): ?string
            {
                return 'demo.ctraderapi.com';
            }

            /** @return array<int, array<string, int>> */
            private function trendbars(): array
            {
                $bars = [];
                $startMinutes = 29800000;

                for ($index = 0; $index < 260; $index++) {
                    $low = 100000 + ($index * 20);

                    $bars[] = [
                        'volume' => 100 + $index,
                        'low' => $low,
                        'deltaOpen' => 5,
                        'deltaClose' => 15,
                        'deltaHigh' => 25,
                        'utcTimestampInMinutes' =>
                            $startMinutes + ($index * 60),
                    ];
                }

                return $bars;
            }
        };
    }
}
