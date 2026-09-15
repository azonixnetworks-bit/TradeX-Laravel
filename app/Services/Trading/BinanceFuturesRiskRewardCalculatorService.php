<?php

namespace App\Services\Trading;

use DomainException;

class BinanceFuturesRiskRewardCalculatorService
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $market
     * @return array<string, mixed>
     */
    public function calculate(array $input, array $market): array
    {
        $symbol = strtoupper(trim((string) ($input['symbol'] ?? '')));
        $side = strtoupper((string) ($input['side'] ?? ''));
        $mode = strtoupper((string) ($input['calculation_mode'] ?? 'RISK_BASED'));

        $instrument = ((array) ($market['instruments'] ?? []))[$symbol] ?? null;

        if (! is_array($instrument)) {
            throw new DomainException('Select a supported Binance Futures instrument from the available list.');
        }

        if (! in_array($side, ['BUY', 'SELL'], true)) {
            throw new DomainException('Trade side must be BUY or SELL.');
        }

        if (! in_array($mode, ['RISK_BASED', 'MARGIN_BASED'], true)) {
            throw new DomainException('Binance Futures calculation mode must be Risk Based or Margin Based.');
        }

        $entry = $this->positiveNumber($input['entry'] ?? null, 'Entry price');
        $stopLoss = $this->positiveNumber($input['stop_loss'] ?? null, 'Stop loss');
        $takeProfit = $this->positiveNumber($input['take_profit'] ?? null, 'Take profit');
        $leverage = $this->leverage($input['leverage'] ?? null, $market);

        $this->validatePriceStructure($side, $entry, $stopLoss, $takeProfit);

        $riskDistance = abs($entry - $stopLoss);
        $rewardDistance = abs($takeProfit - $entry);
        $riskRewardRatio = $rewardDistance / $riskDistance;
        $stopLossPercent = ($riskDistance / $entry) * 100;
        $takeProfitPercent = ($rewardDistance / $entry) * 100;

        $quantityStep = (float) ($instrument['lot_step'] ?? 0.00000001);
        $minimumQuantity = (float) ($instrument['min_lot'] ?? $quantityStep);
        $maximumQuantity = (float) ($instrument['max_lot'] ?? 1000000000.0);

        $riskBudget = null;
        $marginBudget = null;

        if ($mode === 'RISK_BASED') {
            $riskBudget = $this->positiveNumber($input['risk_amount'] ?? null, 'Risk amount');
            $rawQuantity = $riskBudget / $riskDistance;
        } else {
            $marginBudget = $this->positiveNumber($input['margin_amount'] ?? null, 'Margin amount');
            $rawNotional = $marginBudget * $leverage;
            $rawQuantity = $rawNotional / $entry;
        }

        $warnings = [
            'Binance Futures leverage and maintenance-margin tiers depend on the live symbol and position notional. Confirm the current Binance tier before placing a trade.',
            'Liquidation price, trading fees, funding and slippage are not included in this estimate. Confirm liquidation remains beyond the planned Stop Loss.',
            'USDT-denominated values are shown as trading-risk estimates; verify current USDT/USD value if you need strict USD accounting.',
        ];

        $tradableWithinRisk = true;
        $quantity = $this->roundDownToStep(min($rawQuantity, $maximumQuantity), $quantityStep);

        if ($quantity < $minimumQuantity) {
            $tradableWithinRisk = false;
            $quantity = null;
            $warnings[] = 'Calculated quantity is below the TradeX planning minimum. Confirm the live Binance exchangeInfo quantity filter for this symbol.';
        } elseif ($rawQuantity > $maximumQuantity) {
            $warnings[] = 'Calculated quantity exceeds the TradeX planning maximum and has been capped. Confirm the live Binance quantity filter.';
        }

        $positionNotional = $quantity !== null ? $quantity * $entry : null;
        $requiredInitialMargin = $positionNotional !== null ? $positionNotional / $leverage : null;
        $actualRisk = $quantity !== null ? $quantity * $riskDistance : null;
        $potentialProfit = $quantity !== null ? $quantity * $rewardDistance : null;

        $riskUtilization = null;
        if ($mode === 'RISK_BASED' && $riskBudget !== null && $actualRisk !== null) {
            $riskUtilization = ($actualRisk / $riskBudget) * 100;
        } elseif ($mode === 'MARGIN_BASED' && $marginBudget !== null && $requiredInitialMargin !== null) {
            $riskUtilization = ($requiredInitialMargin / $marginBudget) * 100;
        }

        $slRoe = $requiredInitialMargin !== null && $requiredInitialMargin > 0 && $actualRisk !== null
            ? ($actualRisk / $requiredInitialMargin) * 100
            : null;

        $tpRoe = $requiredInitialMargin !== null && $requiredInitialMargin > 0 && $potentialProfit !== null
            ? ($potentialProfit / $requiredInitialMargin) * 100
            : null;

        return [
            'market' => 'binance_futures',
            'market_label' => (string) ($market['label'] ?? 'Binance Futures'),
            'symbol' => $symbol,
            'instrument_name' => (string) ($instrument['name'] ?? $symbol),
            'side' => $side,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'take_profit' => $takeProfit,
            'risk_amount' => $mode === 'RISK_BASED' ? $riskBudget : $actualRisk,
            'margin_amount' => $mode === 'MARGIN_BASED' ? $marginBudget : $requiredInitialMargin,
            'raw_lot' => $rawQuantity,
            'recommended_lot' => $quantity,
            'tradable_within_risk' => $tradableWithinRisk,
            'actual_risk' => $actualRisk,
            'potential_profit' => $potentialProfit,
            'position_units' => $quantity,
            'risk_utilization_percent' => $riskUtilization,
            'risk_reward_ratio' => $riskRewardRatio,
            'stop_loss_distance' => $stopLossPercent,
            'take_profit_distance' => $takeProfitPercent,
            'distance_label' => '%',
            'risk_per_lot' => $riskDistance,
            'profit_per_lot' => $rewardDistance,
            'minimum_lot_risk' => $minimumQuantity * $riskDistance,
            'risk_quote_to_usd_rate' => 1.0,
            'profit_quote_to_usd_rate' => 1.0,
            'quote_to_usd_conversion' => [
                'source' => 'USDT_DENOMINATED',
                'symbol' => 'USDT',
                'risk_rate' => 1.0,
                'profit_rate' => 1.0,
            ],
            'warnings' => $warnings,
            'futures' => [
                'contract_type' => 'USDⓈ-M PERPETUAL',
                'settlement_asset' => 'USDT',
                'calculation_mode' => $mode,
                'leverage' => $leverage,
                'position_quantity' => $quantity,
                'position_notional' => $positionNotional,
                'required_initial_margin' => $requiredInitialMargin,
                'risk_budget' => $riskBudget,
                'margin_budget' => $marginBudget,
                'stop_loss_percent' => $stopLossPercent,
                'take_profit_percent' => $takeProfitPercent,
                'estimated_sl_roe_percent' => $slRoe,
                'estimated_tp_roe_percent' => $tpRoe,
                'leverage_tier_live_check_required' => true,
            ],
            'specification' => [
                'contract_size' => 1.0,
                'min_lot' => $minimumQuantity,
                'max_lot' => $maximumQuantity,
                'lot_step' => $quantityStep,
                'tick_size' => (float) ($instrument['tick_size'] ?? 0.00000001),
                'base_currency' => (string) ($instrument['base_currency'] ?? ''),
                'quote_currency' => 'USDT',
                'broker' => 'Binance',
                'category' => 'USDⓈ-M Perpetual',
                'profile' => (string) ($instrument['spec_profile'] ?? 'Binance USDⓈ-M perpetual risk-planning profile'),
                'status' => (string) ($instrument['spec_status'] ?? 'PARTIALLY VERIFIED'),
                'source' => (string) ($instrument['spec_source'] ?? ($market['spec_source'] ?? 'Binance official Futures documentation')),
                'verified_at' => $instrument['spec_verified_at'] ?? ($market['spec_verified_at'] ?? null),
            ],
        ];
    }

    /** @param array<string, mixed> $market */
    private function leverage(mixed $value, array $market): int
    {
        if (! is_numeric($value)) {
            throw new DomainException('Leverage must be a whole number.');
        }

        $number = (float) $value;
        $leverage = (int) $number;
        $settings = (array) ($market['leverage'] ?? []);
        $minimum = (int) ($settings['min'] ?? 1);
        $maximum = (int) ($settings['max'] ?? 125);

        if (! is_finite($number) || $number !== (float) $leverage || $leverage < $minimum || $leverage > $maximum) {
            throw new DomainException(sprintf('Leverage must be a whole number between %dx and %dx.', $minimum, $maximum));
        }

        return $leverage;
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

    private function validatePriceStructure(string $side, float $entry, float $stopLoss, float $takeProfit): void
    {
        if ($side === 'BUY' && ! ($stopLoss < $entry && $entry < $takeProfit)) {
            throw new DomainException('For a BUY trade, Stop Loss must be below Entry and Take Profit must be above Entry.');
        }

        if ($side === 'SELL' && ! ($takeProfit < $entry && $entry < $stopLoss)) {
            throw new DomainException('For a SELL trade, Take Profit must be below Entry and Stop Loss must be above Entry.');
        }
    }

    private function roundDownToStep(float $value, float $step): float
    {
        if ($value <= 0 || $step <= 0) {
            return 0.0;
        }

        $units = floor(($value + 1.0E-12) / $step);

        return round($units * $step, $this->stepPrecision($step));
    }

    private function stepPrecision(float $step): int
    {
        $formatted = rtrim(rtrim(sprintf('%.10F', $step), '0'), '.');
        $position = strpos($formatted, '.');

        return $position === false ? 0 : strlen($formatted) - $position - 1;
    }
}
