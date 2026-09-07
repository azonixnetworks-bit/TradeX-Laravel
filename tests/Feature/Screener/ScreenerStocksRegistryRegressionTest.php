<?php

namespace Tests\Feature\Screener;

use App\Contracts\Screener\MarketDataProvider;
use App\Services\Screener\IndicatorEngine;
use App\Services\Screener\ScreenerEngine;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ScreenerStocksRegistryRegressionTest extends TestCase
{
    public function test_stocks_use_dedicated_registry_and_exclude_unsupported_timeframes(): void
    {
        Cache::flush();

        config()->set('tradex_screener_stocks.enabled', true);
        config()->set('tradex_screener.timeframes.H1', [
            'label' => '1 Hour',
            'provider_interval' => '1h',
        ]);
        config()->set('tradex_screener_stocks.symbols', [
            [
                'symbol' => 'AAPL',
                'provider_symbol' => 'AAPL',
                'name' => 'Apple Inc.',
                'region' => 'INTERNATIONAL',
                'exchange' => 'NASDAQ',
                'mic_code' => 'XNAS',
                'currency' => 'USD',
                'data_mode' => 'REALTIME',
                'supported_timeframes' => ['H1'],
                'enabled' => true,
            ],
            [
                'symbol' => 'RELIANCE',
                'provider_symbol' => 'RELIANCE',
                'name' => 'Reliance Industries Limited',
                'region' => 'INDIA',
                'exchange' => 'NSE',
                'mic_code' => 'XNSE',
                'currency' => 'INR',
                'data_mode' => 'EOD',
                'supported_timeframes' => ['D1'],
                'enabled' => true,
            ],
        ]);

        $provider = new class implements MarketDataProvider
        {
            /** @var array<int, string> */
            public array $requestedSymbols = [];

            public function name(): string
            {
                return 'TWELVE_DATA_STOCKS';
            }

            public function market(): string
            {
                return 'STOCKS';
            }

            public function configured(): bool
            {
                return true;
            }

            public function timeSeries(
                array $symbols,
                string $interval,
                int $outputSize
            ): array {
                $this->requestedSymbols = $symbols;
                $series = [];

                foreach ($symbols as $symbol) {
                    $series[$symbol] = [];

                    for ($index = 0; $index < 260; $index++) {
                        $close = 100 + ($index * 0.5);
                        $series[$symbol][] = [
                            'datetime_utc' => now('UTC')
                                ->subHours(259 - $index)
                                ->toIso8601String(),
                            'open' => $close - 0.2,
                            'high' => $close + 0.4,
                            'low' => $close - 0.4,
                            'close' => $close,
                            'volume' => 1000 + $index,
                        ];
                    }
                }

                return $series;
            }

            public function health(): array
            {
                return [
                    'provider' => 'TWELVE_DATA_STOCKS',
                    'market' => 'STOCKS',
                    'configured' => true,
                    'status' => 'HEALTHY',
                ];
            }
        };

        $engine = new ScreenerEngine(
            [$provider],
            new IndicatorEngine()
        );

        $this->assertTrue($engine->markets()['STOCKS']['enabled']);
        $this->assertSame('ACTIVE', $engine->markets()['STOCKS']['status']);
        $this->assertCount(2, $engine->symbols('STOCKS'));

        $result = $engine->screen([
            'market' => 'STOCKS',
            'timeframe' => 'H1',
            'region' => 'INTERNATIONAL',
            'exchange' => 'NASDAQ',
        ]);

        $this->assertSame(['AAPL'], $provider->requestedSymbols);
        $this->assertSame(1, $result['count']);
        $this->assertSame('AAPL', $result['results'][0]['symbol']);
        $this->assertSame('INTERNATIONAL', $result['results'][0]['region']);
        $this->assertSame('NASDAQ', $result['results'][0]['exchange']);
        $this->assertSame('USD', $result['results'][0]['currency']);
        $this->assertSame('REALTIME', $result['results'][0]['data_mode']);
    }
}
