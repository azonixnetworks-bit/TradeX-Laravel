<?php

namespace App\Services\Trading;

use DomainException;

class RiskRewardCalculatorService
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $marketKey = strtolower((string) ($input['market'] ?? ''));
        $symbol = strtoupper(trim((string) ($input['symbol'] ?? '')));
        $side = strtoupper((string) ($input['side'] ?? ''));

        $markets = (array) config('trading_instruments.markets', []);
        $market = $markets[$marketKey] ?? null;

        if (! is_array($market)) {
            throw new DomainException('Unsupported market type.');
        }

        $instruments = (array) ($market['instruments'] ?? []);
        $instrument = $instruments[$symbol] ?? null;

        if (! is_array($instrument)) {
            throw new DomainException('Select a valid instrument from the selected market.');
        }

        if (! in_array($side, ['BUY', 'SELL'], true)) {
            throw new DomainException('Trade side must be BUY or SELL.');
        }

        $entry = $this->positiveNumber($input['entry'] ?? null, 'Entry price');
        $stopLoss = $this->positiveNumber($input['stop_loss'] ?? null, 'Stop loss');
        $takeProfit = $this->positiveNumber($input['take_profit'] ?? null, 'Take profit');
        $riskAmount = $this->positiveNumber($input['risk_amount'] ?? null, 'Risk amount');

        $this->validatePriceStructure(
            $side,
            $entry,
            $stopLoss,
            $takeProfit
        );

        $contractSize = (float) ($instrument['contract_size'] ?? 0);
        $minLot = (float) ($instrument['min_lot'] ?? 0);
        $maxLot = (float) ($instrument['max_lot'] ?? 0);
        $lotStep = (float) ($instrument['lot_step'] ?? 0);
        $distanceUnit = (float) ($instrument['distance_unit'] ?? 0);

        if (
            $contractSize <= 0
            || $minLot <= 0
            || $maxLot < $minLot
            || $lotStep <= 0
            || $distanceUnit <= 0
        ) {
            throw new DomainException('The selected instrument specification is incomplete.');
        }

        $riskDistance = abs($entry - $stopLoss);
        $rewardDistance = abs($takeProfit - $entry);

        if ($riskDistance <= 0 || $rewardDistance <= 0) {
            throw new DomainException('Stop loss and take profit must be different from entry price.');
        }

        $baseCurrency = (string) ($instrument['base_currency'] ?? '');
        $quoteCurrency = (string) ($instrument['quote_currency'] ?? '');

        $riskQuoteToUsd = $this->quoteToUsdRate(
            $baseCurrency,
            $quoteCurrency,
            $stopLoss
        );

        $profitQuoteToUsd = $this->quoteToUsdRate(
            $baseCurrency,
            $quoteCurrency,
            $takeProfit
        );

        $riskPerLotUsd = $riskDistance * $contractSize * $riskQuoteToUsd;
        $profitPerLotUsd = $rewardDistance * $contractSize * $profitQuoteToUsd;

        if ($riskPerLotUsd <= 0) {
            throw new DomainException('Unable to calculate risk for the selected instrument.');
        }

        $rawLot = $riskAmount / $riskPerLotUsd;
        $minimumLotRisk = $riskPerLotUsd * $minLot;
        $warnings = [];
        $tradableWithinRisk = true;
        $recommendedLot = null;

        if ($rawLot < $minLot) {
            $tradableWithinRisk = false;
            $warnings[] = sprintf(
                'Requested risk is below the minimum tradable volume. Minimum lot %.4f would risk approximately $%.2f.',
                $minLot,
                $minimumLotRisk
            );
        } else {
            $cappedRawLot = min($rawLot, $maxLot);
            $recommendedLot = $this->roundDownToStep(
                $cappedRawLot,
                $lotStep
            );

            if ($recommendedLot < $minLot) {
                $tradableWithinRisk = false;
                $recommendedLot = null;
                $warnings[] = sprintf(
                    'Requested risk cannot be matched within the configured minimum lot %.4f.',
                    $minLot
                );
            } elseif ($rawLot > $maxLot) {
                $warnings[] = sprintf(
                    'Calculated lot size exceeds the configured maximum %.4f. Result is capped at the maximum volume.',
                    $maxLot
                );
            }
        }

        $actualRisk = $recommendedLot !== null
            ? $riskPerLotUsd * $recommendedLot
            : null;

        $potentialProfit = $recommendedLot !== null
            ? $profitPerLotUsd * $recommendedLot
            : null;

        $riskRewardRatio = $rewardDistance / $riskDistance;
        $slDistanceUnits = $riskDistance / $distanceUnit;
        $tpDistanceUnits = $rewardDistance / $distanceUnit;

        return [
            'market' => $marketKey,
            'market_label' => (string) ($market['label'] ?? ucfirst($marketKey)),
            'symbol' => $symbol,
            'instrument_name' => (string) ($instrument['name'] ?? $symbol),
            'side' => $side,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_amount' => $riskAmount,
            'raw_lot' => $rawLot,
            'recommended_lot' => $recommendedLot,
            'tradable_within_risk' => $tradableWithinRisk,
            'actual_risk' => $actualRisk,
            'potential_profit' => $potentialProfit,
            'risk_reward_ratio' => $riskRewardRatio,
            'stop_loss_distance' => $slDistanceUnits,
            'take_profit_distance' => $tpDistanceUnits,
            'distance_label' => (string) ($instrument['distance_label'] ?? 'points'),
            'risk_per_lot' => $riskPerLotUsd,
            'profit_per_lot' => $profitPerLotUsd,
            'minimum_lot_risk' => $minimumLotRisk,
            'risk_quote_to_usd_rate' => $riskQuoteToUsd,
            'profit_quote_to_usd_rate' => $profitQuoteToUsd,
            'warnings' => $warnings,
            'specification' => [
                'contract_size' => $contractSize,
                'min_lot' => $minLot,
                'max_lot' => $maxLot,
                'lot_step' => $lotStep,
                'tick_size' => (float) ($instrument['tick_size'] ?? 0),
                'base_currency' => $baseCurrency,
                'quote_currency' => $quoteCurrency,
                'profile' => (string) ($instrument['spec_profile'] ?? 'TradeX reference'),
                'status' => (string) ($instrument['spec_status'] ?? $market['spec_status'] ?? 'REFERENCE'),
                'source' => (string) ($instrument['spec_source'] ?? $market['spec_source'] ?? 'TradeX reference profile'),
                'verified_at' => $instrument['spec_verified_at']
                    ?? $market['spec_verified_at']
                    ?? null,
            ],
        ];
    }

    private function positiveNumber(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new DomainException($label.' must be a valid number.');
        }

        $number = (float) $value;

        if (! is_finite($number) || $number <= 0) {
            throw new DomainException($label.' must be greater than zero.');
        }

        return $number;
    }

    private function validatePriceStructure(
        string $side,
        float $entry,
        float $stopLoss,
        float $takeProfit
    ): void {
        if ($side === 'BUY' && ! ($stopLoss < $entry && $entry < $takeProfit)) {
            throw new DomainException('For a BUY trade, Stop Loss must be below Entry and Take Profit must be above Entry.');
        }

        if ($side === 'SELL' && ! ($takeProfit < $entry && $entry < $stopLoss)) {
            throw new DomainException('For a SELL trade, Take Profit must be below Entry and Stop Loss must be above Entry.');
        }
    }

    private function quoteToUsdRate(
        string $baseCurrency,
        string $quoteCurrency,
        float $conversionPrice
    ): float {
        $baseCurrency = strtoupper($baseCurrency);
        $quoteCurrency = strtoupper($quoteCurrency);

        if ($quoteCurrency === 'USD') {
            return 1.0;
        }

        if ($baseCurrency === 'USD' && $conversionPrice > 0) {
            return 1.0 / $conversionPrice;
        }

        throw new DomainException(
            'This instrument needs an external quote-currency to USD conversion rate. It is not enabled in this release.'
        );
    }

    private function roundDownToStep(float $value, float $step): float
    {
        if ($value <= 0 || $step <= 0) {
            return 0.0;
        }

        $units = floor(($value + 1.0E-12) / $step);
        $rounded = $units * $step;

        return round(
            $rounded,
            $this->stepPrecision($step)
        );
    }

    private function stepPrecision(float $step): int
    {
        $formatted = rtrim(
            rtrim(sprintf('%.10F', $step), '0'),
            '.'
        );

        $position = strpos($formatted, '.');

        return $position === false
            ? 0
            : strlen($formatted) - $position - 1;
    }
}