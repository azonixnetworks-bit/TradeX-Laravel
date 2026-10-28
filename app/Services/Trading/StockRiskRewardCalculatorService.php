<?php

namespace App\Services\Trading;

use DomainException;

class StockRiskRewardCalculatorService
{
    /** @param array<string, mixed> $input */
    public function calculate(array $input): array
    {
        $side = strtoupper((string) ($input['side'] ?? 'BUY'));
        $entry = $this->positive($input['entry'] ?? null, 'Entry price');
        $stop = $this->positive($input['stop_loss'] ?? null, 'Stop loss');
        $target = $this->optionalPositive($input['take_profit'] ?? null, 'Take profit');
        $riskUsd = $this->positive($input['risk_amount'] ?? null, 'Risk amount');
        $currency = strtoupper(trim((string) ($input['currency'] ?? '')));
        $fractional = (bool) ($input['fractional_enabled'] ?? false);
        $shareStep = $fractional
            ? $this->positive($input['share_step'] ?? 0.0001, 'Share step')
            : 1.0;

        if (! in_array($side, ['BUY', 'SELL'], true)) {
            throw new DomainException('Trade side must be BUY or SELL.');
        }
        if ($side === 'SELL' && ! (bool) ($input['short_selling_supported'] ?? false)) {
            throw new DomainException('Short selling is unavailable for the selected market or broker.');
        }
        $this->validatePrices($side, $entry, $stop, $target);

        $fxRate = $currency === 'USD'
            ? 1.0
            : $this->positive($input['instrument_to_usd_rate'] ?? null, $currency.' to USD rate');
        $fxSource = $currency === 'USD'
            ? 'USD_BASE'
            : trim((string) ($input['fx_rate_source'] ?? ''));
        if ($currency !== 'USD' && $fxSource === '') {
            throw new DomainException('Enter the FX rate source for this non-USD stock calculation.');
        }

        $feesPerShare = $this->nonNegative($input['fees_per_share'] ?? 0, 'Fees per share');
        $slippagePercent = $this->nonNegative($input['slippage_percent'] ?? 0, 'Slippage');
        if ($slippagePercent > 100) {
            throw new DomainException('Slippage must be between 0% and 100%.');
        }

        $riskDistance = abs($entry - $stop);
        $riskPerShareLocal = $riskDistance + $feesPerShare + ($entry * $slippagePercent / 100);
        $riskBudgetLocal = $riskUsd / $fxRate;
        $rawShares = $riskBudgetLocal / $riskPerShareLocal;
        $shares = $this->roundDown($rawShares, $shareStep);

        $buyingPower = $this->optionalPositive($input['buying_power'] ?? null, 'Buying power');
        if ($buyingPower !== null) {
            $affordable = $this->roundDown($buyingPower / $entry, $shareStep);
            $shares = min($shares, $affordable);
        }
        if ($shares < $shareStep) {
            throw new DomainException('The selected risk or buying power is below the minimum share step.');
        }

        $actualRiskLocal = $shares * $riskPerShareLocal;
        $potentialProfitLocal = $target !== null
            ? max(0.0, ($shares * abs($target - $entry)) - ($shares * $feesPerShare))
            : null;

        return [
            'market' => 'stocks',
            'market_label' => 'Stocks',
            'symbol' => strtoupper(trim((string) ($input['symbol'] ?? 'MANUAL'))),
            'instrument_name' => trim((string) ($input['company_name'] ?? 'Manual stock')),
            'side' => $side,
            'entry' => $entry,
            'stop_loss' => $stop,
            'take_profit' => $target,
            'risk_amount' => $riskUsd,
            'recommended_lot' => $shares,
            'position_units' => $shares,
            'position_unit_label' => $fractional ? 'shares' : 'whole shares',
            'tradable_within_risk' => true,
            'actual_risk' => $actualRiskLocal * $fxRate,
            'potential_profit' => $potentialProfitLocal !== null ? $potentialProfitLocal * $fxRate : null,
            'risk_reward_ratio' => $target !== null ? abs($target - $entry) / $riskDistance : null,
            'required_investment' => $shares * $entry,
            'required_investment_usd' => $shares * $entry * $fxRate,
            'stop_loss_distance' => $riskDistance,
            'take_profit_distance' => $target !== null ? abs($target - $entry) : null,
            'distance_label' => $currency,
            'warnings' => [
                'Position size is an estimate. Confirm broker fees, lot rules, settlement and short-selling eligibility before trading.',
            ],
            'fx_conversion' => [
                'instrument_currency' => $currency,
                'instrument_to_usd_rate' => $fxRate,
                'source' => $fxSource,
                'observed_at' => $input['fx_rate_observed_at'] ?? null,
            ],
            'specification' => [
                'region' => strtoupper((string) ($input['region'] ?? '')),
                'exchange' => strtoupper((string) ($input['exchange'] ?? '')),
                'currency' => $currency,
                'fractional_enabled' => $fractional,
                'share_step' => $shareStep,
                'data_mode' => (string) ($input['data_mode'] ?? 'MANUAL'),
            ],
        ];
    }

    private function validatePrices(string $side, float $entry, float $stop, ?float $target): void
    {
        if ($side === 'BUY' && $stop >= $entry) {
            throw new DomainException('For a BUY trade, Stop Loss must be below Entry.');
        }
        if ($side === 'SELL' && $stop <= $entry) {
            throw new DomainException('For a SELL trade, Stop Loss must be above Entry.');
        }
        if ($target !== null && $side === 'BUY' && $target <= $entry) {
            throw new DomainException('For a BUY trade, Take Profit must be above Entry.');
        }
        if ($target !== null && $side === 'SELL' && $target >= $entry) {
            throw new DomainException('For a SELL trade, Take Profit must be below Entry.');
        }
    }

    private function positive(mixed $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new DomainException($label.' must be greater than zero.');
        }

        return (float) $value;
    }

    private function optionalPositive(mixed $value, string $label): ?float
    {
        return $value === null || $value === '' ? null : $this->positive($value, $label);
    }

    private function nonNegative(mixed $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new DomainException($label.' must be zero or greater.');
        }

        return (float) $value;
    }

    private function roundDown(float $value, float $step): float
    {
        return floor(($value + 1.0E-12) / $step) * $step;
    }
}
