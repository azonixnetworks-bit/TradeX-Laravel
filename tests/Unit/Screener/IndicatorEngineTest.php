<?php

namespace Tests\Unit\Screener;

use App\Services\Screener\IndicatorEngine;
use PHPUnit\Framework\TestCase;

class IndicatorEngineTest extends TestCase
{
    public function test_rising_market_produces_bullish_alignment_and_indicators(): void
    {
        $analysis = (new IndicatorEngine())
            ->analyse(
                $this->candles(
                    start: 100,
                    step: 0.10
                )
            );

        $this->assertSame(
            'BULLISH',
            $analysis['trend']
        );

        $this->assertSame(
            'BULLISH',
            $analysis['ema_alignment']
        );

        $this->assertGreaterThan(
            70,
            $analysis['rsi_14']
        );

        $this->assertNotNull(
            $analysis['ema_200']
        );

        $this->assertNotNull(
            $analysis['macd_signal']
        );

        $this->assertGreaterThan(
            0,
            $analysis['atr_14']
        );

        $this->assertSame(
            260,
            $analysis['bars']
        );
    }

    public function test_falling_market_produces_bearish_alignment(): void
    {
        $analysis = (new IndicatorEngine())
            ->analyse(
                $this->candles(
                    start: 200,
                    step: -0.10
                )
            );

        $this->assertSame(
            'BEARISH',
            $analysis['trend']
        );

        $this->assertSame(
            'BEARISH',
            $analysis['ema_alignment']
        );

        $this->assertLessThan(
            30,
            $analysis['rsi_14']
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function candles(
        float $start,
        float $step
    ): array {
        $candles = [];

        for ($index = 0; $index < 260; $index++) {
            $close = $start + ($step * $index);

            $candles[] = [
                'datetime_utc' => sprintf(
                    '2026-01-%02dT00:00:00+00:00',
                    ($index % 28) + 1
                ),
                'open' => $close - 0.03,
                'high' => $close + 0.08,
                'low' => $close - 0.08,
                'close' => $close,
                'volume' => null,
            ];
        }

        return $candles;
    }
}
