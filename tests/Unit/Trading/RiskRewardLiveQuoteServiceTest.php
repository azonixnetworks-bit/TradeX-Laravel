<?php

namespace Tests\Unit\Trading;

use App\Contracts\Screener\DerivWebSocketTransport;
use App\Services\Trading\RiskRewardLiveQuoteService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class RiskRewardLiveQuoteServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config()->set('tradex_screener.enabled', true);
    }

    public function test_forex_quote_uses_allowed_catalog_symbol_and_preserves_precision(): void
    {
        config()->set('tradex_screener.providers.twelve_data.enabled', true);
        config()->set('tradex_screener.providers.twelve_data.api_key', 'test-key');
        config()->set('tradex_screener.providers.twelve_data.base_url', 'https://quotes.test');

        Http::fake([
            'https://quotes.test/time_series*' => Http::response([
                'meta' => ['symbol' => 'EUR/USD'],
                'values' => [
                    [
                        'datetime' => '2026-10-29 08:00:00',
                        'open' => '1.08500',
                        'high' => '1.08600',
                        'low' => '1.08450',
                        'close' => '1.08510',
                    ],
                    [
                        'datetime' => '2026-10-29 08:05:00',
                        'open' => '1.08510',
                        'high' => '1.08580',
                        'low' => '1.08490',
                        'close' => '1.08543',
                    ],
                ],
            ]),
        ]);

        $quote = app(RiskRewardLiveQuoteService::class)->quote('forex', 'EURUSD');

        $this->assertSame('EURUSD', $quote['symbol']);
        $this->assertSame('1.08543', $quote['display_price']);
        $this->assertSame('TWELVE_DATA', $quote['provider']);
    }

    public function test_forex_quote_falls_back_to_public_realtime_tick_when_twelve_data_is_not_configured(): void
    {
        config()->set('tradex_screener.providers.twelve_data.enabled', true);
        config()->set('tradex_screener.providers.twelve_data.api_key', '');

        $this->mock(
            DerivWebSocketTransport::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('requestMany')
                    ->once()
                    ->with([[
                        'ticks' => 'frxEURUSD',
                        'subscribe' => 1,
                        'req_id' => 1,
                    ]])
                    ->andReturn([[
                        'tick' => [
                            'quote' => 1.08654,
                            'pip_size' => 5,
                            'epoch' => 1793260800,
                        ],
                    ]]);
            }
        );

        $quote = app(RiskRewardLiveQuoteService::class)->quote('forex', 'EURUSD');

        $this->assertSame('1.08654', $quote['display_price']);
        $this->assertSame('DERIV_PUBLIC_FOREX', $quote['provider']);
    }

    public function test_deriv_buy_quote_uses_realtime_ask_from_ctrader_service(): void
    {
        config()->set('tradex_screener.providers.deriv.enabled', true);
        config()->set('tradex_screener.providers.deriv.base_url', 'https://deriv.test');

        Http::fake([
            'https://deriv.test/api/deriv/price/*' => Http::response([
                'symbol' => 'Boom 1000 Index',
                'digits' => 2,
                'bid' => 1234.12,
                'ask' => 1234.34,
                'updated_at' => '2026-10-29T08:05:00Z',
            ]),
        ]);

        $quote = app(RiskRewardLiveQuoteService::class)->quote(
            'deriv',
            'BOOM1000',
            'BUY'
        );

        $this->assertSame('BOOM1000', $quote['symbol']);
        $this->assertSame('1234.34', $quote['display_price']);
        $this->assertSame('DERIV', $quote['provider']);
        Http::assertSent(fn ($request): bool => str_contains(
            urldecode($request->url()),
            '/api/deriv/price/Boom 1000 Index'
        ));
    }

    public function test_deriv_quote_falls_back_to_public_realtime_tick_when_ctrader_is_not_configured(): void
    {
        config()->set('tradex_screener.providers.deriv.enabled', false);

        $this->mock(
            DerivWebSocketTransport::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('requestMany')
                    ->once()
                    ->with([[
                        'ticks' => 'BOOM1000',
                        'subscribe' => 1,
                        'req_id' => 1,
                    ]])
                    ->andReturn([[
                        'tick' => [
                            'quote' => 14949.294,
                            'pip_size' => 3,
                            'epoch' => 1793260800,
                        ],
                    ]]);
            }
        );

        $quote = app(RiskRewardLiveQuoteService::class)->quote(
            'deriv',
            'BOOM1000',
            'BUY'
        );

        $this->assertSame('14949.294', $quote['display_price']);
        $this->assertSame('DERIV_PUBLIC', $quote['provider']);
    }

    public function test_binance_futures_quote_uses_live_usdt_perpetual_candle(): void
    {
        config()->set('tradex_screener.providers.binance_futures.enabled', true);
        config()->set('tradex_screener.providers.binance_futures.base_url', 'https://futures.test');

        Http::fake([
            'https://futures.test/fapi/v1/klines*' => Http::response([
                [1793260800000, '60000', '60100', '59900', '60050.5', '12'],
                [1793260860000, '60050.5', '60200', '60020', '60125.25', '8'],
            ]),
        ]);

        $quote = app(RiskRewardLiveQuoteService::class)->quote(
            'binance_futures',
            'BTCUSDT'
        );

        $this->assertSame('BTCUSDT', $quote['symbol']);
        $this->assertSame('60125.25000000', $quote['display_price']);
        $this->assertSame('BINANCE_FUTURES', $quote['provider']);
    }

    public function test_crypto_cfd_quote_maps_usd_symbol_to_binance_usdt_market(): void
    {
        config()->set('tradex_screener.providers.binance.enabled', true);
        config()->set('tradex_screener.providers.binance.base_url', 'https://spot.test');

        Http::fake([
            'https://spot.test/api/v3/klines*' => Http::response([
                [1793260800000, '4000', '4050', '3990', '4020.15', '12'],
                [1793260860000, '4020.15', '4060', '4010', '4042.37', '8'],
            ]),
        ]);

        $quote = app(RiskRewardLiveQuoteService::class)->quote('crypto', 'ETHUSD');

        $this->assertSame('ETHUSD', $quote['symbol']);
        $this->assertSame('4042.37', $quote['display_price']);
        $this->assertSame('BINANCE', $quote['provider']);
        Http::assertSent(fn ($request): bool => str_contains(
            $request->url(),
            'symbol=ETHUSDT'
        ));
    }
}
