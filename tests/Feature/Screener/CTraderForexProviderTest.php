<?php

namespace Tests\Feature\Screener;

use App\Contracts\Screener\CTraderJsonTransport;
use App\Services\Screener\Providers\CTraderForexProvider;
use App\Services\Screener\Providers\TwelveDataForexProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CTraderForexProviderTest extends TestCase
{
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
        config()->set('tradex_screener_ctrader.output_size', 260);
        config()->set('tradex_screener_ctrader.fallback_to_twelve_data', false);
    }

    public function test_ctrader_forex_provider_authenticates_resolves_symbol_and_normalises_trendbars(): void
    {
        $transport = $this->fakeTransport();
        $provider = new CTraderForexProvider(
            $transport,
            new TwelveDataForexProvider()
        );

        $series = $provider->timeSeries(
            ['EUR/USD'],
            '1h',
            260
        );

        $this->assertArrayHasKey('EUR/USD', $series);
        $this->assertCount(2, $series['EUR/USD']);
        $this->assertSame(1.10000, $series['EUR/USD'][0]['low']);
        $this->assertSame(1.10020, $series['EUR/USD'][0]['open']);
        $this->assertSame(1.10050, $series['EUR/USD'][0]['high']);
        $this->assertSame(1.10040, $series['EUR/USD'][0]['close']);
        $this->assertSame(321.0, $series['EUR/USD'][0]['volume']);
        $this->assertSame('CTRADER_IC_MARKETS', $provider->name());

        $this->assertCount(2, $transport->exchanges);

        $trendSteps = $transport->exchanges[1];
        $this->assertSame(2100, $trendSteps[0]['request']['payloadType']);
        $this->assertSame(2102, $trendSteps[1]['request']['payloadType']);
        $this->assertSame(2137, $trendSteps[2]['request']['payloadType']);
        $this->assertSame(9, $trendSteps[2]['request']['payload']['period']);
        $this->assertSame(101, $trendSteps[2]['request']['payload']['symbolId']);
        $this->assertSame(260, $trendSteps[2]['request']['payload']['count']);
        $this->assertGreaterThanOrEqual(200, $trendSteps[2]['delay_ms']);

        $health = $provider->health();
        $this->assertSame('CTRADER_IC_MARKETS', $health['provider']);
        $this->assertTrue($health['configured']);
        $this->assertSame('HEALTHY', $health['status']);
        $this->assertSame('demo.ctraderapi.com', $health['base_host']);
        $this->assertArrayNotHasKey('access_token', $health);
        $this->assertArrayNotHasKey('client_secret', $health);
    }

    public function test_twelve_data_remains_available_when_ctrader_is_not_configured(): void
    {
        config()->set('tradex_screener_ctrader.enabled', false);
        config()->set('tradex_screener_ctrader.fallback_to_twelve_data', true);
        config()->set('tradex_screener.providers.twelve_data.enabled', true);
        config()->set(
            'tradex_screener.providers.twelve_data.api_key',
            'twelve-test-key'
        );

        Http::fake([
            'https://api.twelvedata.com/*' => Http::response([
                'meta' => [
                    'symbol' => 'EUR/USD',
                    'interval' => '1h',
                ],
                'values' => [
                    [
                        'datetime' => '2026-09-08 08:00:00',
                        'open' => '1.1000',
                        'high' => '1.1010',
                        'low' => '1.0990',
                        'close' => '1.1005',
                    ],
                    [
                        'datetime' => '2026-09-08 09:00:00',
                        'open' => '1.1005',
                        'high' => '1.1020',
                        'low' => '1.1000',
                        'close' => '1.1015',
                    ],
                ],
                'status' => 'ok',
            ], 200),
        ]);

        $provider = new CTraderForexProvider(
            $this->fakeTransport(),
            new TwelveDataForexProvider()
        );

        $series = $provider->timeSeries(
            ['EUR/USD'],
            '1h',
            2
        );

        $this->assertArrayHasKey('EUR/USD', $series);
        $this->assertSame('TWELVE_DATA', $provider->name());
        Http::assertSentCount(1);
    }

    private function fakeTransport(): object
    {
        return new class implements CTraderJsonTransport
        {
            /** @var array<int, array<int, array<string, mixed>>> */
            public array $exchanges = [];

            public function exchange(array $steps): array
            {
                $this->exchanges[] = $steps;
                $responses = [];

                foreach ($steps as $step) {
                    $request = $step['request'];
                    $type = (int) $request['payloadType'];
                    $responseType = (int) $step['expect_payload_type'];
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
                            'trendbar' => [
                                [
                                    'volume' => 321,
                                    'low' => 110000,
                                    'deltaOpen' => 20,
                                    'deltaClose' => 40,
                                    'deltaHigh' => 50,
                                    'utcTimestampInMinutes' => 29816640,
                                ],
                                [
                                    'volume' => 400,
                                    'low' => 110040,
                                    'deltaOpen' => 10,
                                    'deltaClose' => 60,
                                    'deltaHigh' => 80,
                                    'utcTimestampInMinutes' => 29816700,
                                ],
                            ],
                        ];
                    }

                    $responses[] = [
                        'clientMsgId' => $request['clientMsgId'],
                        'payloadType' => $responseType,
                        'payload' => $payload,
                    ];
                }

                return $responses;
            }

            public function endpointHost(): ?string
            {
                return 'demo.ctraderapi.com';
            }
        };
    }
}
