<?php

namespace App\Services\Screener;

class IndicatorEngine
{
    /**
     * @param  array<int, array<string, mixed>>  $candles
     * @return array<string, mixed>
     */
    public function analyse(array $candles): array
    {
        $candles = array_values(array_filter(
            $candles,
            static fn (array $candle): bool =>
                isset(
                    $candle['open'],
                    $candle['high'],
                    $candle['low'],
                    $candle['close']
                )
        ));

        if (count($candles) < 2) {
            return $this->emptyAnalysis();
        }

        $closes = array_map(
            static fn (array $candle): float => (float) $candle['close'],
            $candles
        );

        $latest = $candles[array_key_last($candles)];
        $previous = $candles[count($candles) - 2];

        $ema20 = $this->lastEma($closes, 20);
        $ema50 = $this->lastEma($closes, 50);
        $ema200 = $this->lastEma($closes, 200);
        $rsi = $this->rsi($closes, 14);
        $atr = $this->atr($candles, 14);
        $macd = $this->macd($closes);

        $price = (float) $latest['close'];
        $previousClose = (float) $previous['close'];
        $changePercent = $previousClose !== 0.0
            ? (($price - $previousClose) / $previousClose) * 100
            : null;

        $emaAlignment = $this->emaAlignment(
            $ema20,
            $ema50,
            $ema200
        );

        $trend = 'SIDEWAYS';

        if (
            $emaAlignment === 'BULLISH'
            && $ema20 !== null
            && $price > $ema20
        ) {
            $trend = 'BULLISH';
        } elseif (
            $emaAlignment === 'BEARISH'
            && $ema20 !== null
            && $price < $ema20
        ) {
            $trend = 'BEARISH';
        }

        return [
            'price' => $this->round($price),
            'open' => $this->round((float) $latest['open']),
            'high' => $this->round((float) $latest['high']),
            'low' => $this->round((float) $latest['low']),
            'volume' => isset($latest['volume'])
                && $latest['volume'] !== null
                    ? $this->round((float) $latest['volume'], 4)
                    : null,
            'change_percent' => $changePercent !== null
                ? $this->round($changePercent, 4)
                : null,
            'change_basis' => 'PREVIOUS_CANDLE',
            'trend' => $trend,
            'ema_alignment' => $emaAlignment,
            'ema_20' => $this->roundNullable($ema20),
            'ema_50' => $this->roundNullable($ema50),
            'ema_200' => $this->roundNullable($ema200),
            'rsi_14' => $this->roundNullable($rsi, 2),
            'atr_14' => $this->roundNullable($atr),
            'atr_percent' => $atr !== null && $price > 0
                ? $this->round(($atr / $price) * 100, 4)
                : null,
            'macd' => $this->roundNullable($macd['line']),
            'macd_signal' => $this->roundNullable($macd['signal']),
            'macd_histogram' => $this->roundNullable($macd['histogram']),
            'candle_time_utc' => $latest['datetime_utc'] ?? null,
            'bars' => count($candles),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAnalysis(): array
    {
        return [
            'price' => null,
            'open' => null,
            'high' => null,
            'low' => null,
            'volume' => null,
            'change_percent' => null,
            'change_basis' => 'PREVIOUS_CANDLE',
            'trend' => 'SIDEWAYS',
            'ema_alignment' => 'MIXED',
            'ema_20' => null,
            'ema_50' => null,
            'ema_200' => null,
            'rsi_14' => null,
            'atr_14' => null,
            'atr_percent' => null,
            'macd' => null,
            'macd_signal' => null,
            'macd_histogram' => null,
            'candle_time_utc' => null,
            'bars' => 0,
        ];
    }

    /**
     * @param  array<int, float>  $values
     */
    private function lastEma(array $values, int $period): ?float
    {
        $series = $this->emaSeries($values, $period);

        for ($index = count($series) - 1; $index >= 0; $index--) {
            if ($series[$index] !== null) {
                return (float) $series[$index];
            }
        }

        return null;
    }

    /**
     * @param  array<int, float>  $values
     * @return array<int, float|null>
     */
    private function emaSeries(array $values, int $period): array
    {
        $count = count($values);
        $result = array_fill(0, $count, null);

        if ($period < 1 || $count < $period) {
            return $result;
        }

        $seed = array_sum(array_slice($values, 0, $period))
            / $period;

        $result[$period - 1] = $seed;
        $multiplier = 2 / ($period + 1);
        $previous = $seed;

        for ($index = $period; $index < $count; $index++) {
            $current = (
                ($values[$index] - $previous)
                * $multiplier
            ) + $previous;

            $result[$index] = $current;
            $previous = $current;
        }

        return $result;
    }

    /**
     * @param  array<int, float>  $closes
     */
    private function rsi(array $closes, int $period): ?float
    {
        if (count($closes) <= $period) {
            return null;
        }

        $gain = 0.0;
        $loss = 0.0;

        for ($index = 1; $index <= $period; $index++) {
            $change = $closes[$index] - $closes[$index - 1];

            if ($change >= 0) {
                $gain += $change;
            } else {
                $loss += abs($change);
            }
        }

        $averageGain = $gain / $period;
        $averageLoss = $loss / $period;

        for ($index = $period + 1; $index < count($closes); $index++) {
            $change = $closes[$index] - $closes[$index - 1];
            $currentGain = max($change, 0);
            $currentLoss = max(-$change, 0);

            $averageGain = (
                ($averageGain * ($period - 1))
                + $currentGain
            ) / $period;

            $averageLoss = (
                ($averageLoss * ($period - 1))
                + $currentLoss
            ) / $period;
        }

        if ($averageLoss === 0.0 && $averageGain === 0.0) {
            return 50.0;
        }

        if ($averageLoss === 0.0) {
            return 100.0;
        }

        $relativeStrength = $averageGain / $averageLoss;

        return 100 - (100 / (1 + $relativeStrength));
    }

    /**
     * @param  array<int, array<string, mixed>>  $candles
     */
    private function atr(array $candles, int $period): ?float
    {
        if (count($candles) <= $period) {
            return null;
        }

        $trueRanges = [];

        for ($index = 1; $index < count($candles); $index++) {
            $high = (float) $candles[$index]['high'];
            $low = (float) $candles[$index]['low'];
            $previousClose = (float) $candles[$index - 1]['close'];

            $trueRanges[] = max(
                $high - $low,
                abs($high - $previousClose),
                abs($low - $previousClose)
            );
        }

        if (count($trueRanges) < $period) {
            return null;
        }

        $average = array_sum(array_slice($trueRanges, 0, $period))
            / $period;

        for ($index = $period; $index < count($trueRanges); $index++) {
            $average = (
                ($average * ($period - 1))
                + $trueRanges[$index]
            ) / $period;
        }

        return $average;
    }

    /**
     * @param  array<int, float>  $closes
     * @return array{line: float|null, signal: float|null, histogram: float|null}
     */
    private function macd(array $closes): array
    {
        $ema12 = $this->emaSeries($closes, 12);
        $ema26 = $this->emaSeries($closes, 26);
        $macdValues = [];

        for ($index = 0; $index < count($closes); $index++) {
            if ($ema12[$index] !== null && $ema26[$index] !== null) {
                $macdValues[] = $ema12[$index] - $ema26[$index];
            }
        }

        if ($macdValues === []) {
            return [
                'line' => null,
                'signal' => null,
                'histogram' => null,
            ];
        }

        $line = $macdValues[array_key_last($macdValues)];
        $signal = $this->lastEma($macdValues, 9);

        return [
            'line' => $line,
            'signal' => $signal,
            'histogram' => $signal !== null
                ? $line - $signal
                : null,
        ];
    }

    private function emaAlignment(
        ?float $ema20,
        ?float $ema50,
        ?float $ema200
    ): string {
        if ($ema20 === null || $ema50 === null || $ema200 === null) {
            return 'MIXED';
        }

        if ($ema20 > $ema50 && $ema50 > $ema200) {
            return 'BULLISH';
        }

        if ($ema20 < $ema50 && $ema50 < $ema200) {
            return 'BEARISH';
        }

        return 'MIXED';
    }

    private function round(float $value, int $precision = 8): float
    {
        return round($value, $precision);
    }

    private function roundNullable(
        ?float $value,
        int $precision = 8
    ): ?float {
        return $value !== null
            ? $this->round($value, $precision)
            : null;
    }
}
