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
        $marginMode = strtoupper((string) ($input['margin_mode'] ?? 'ISOLATED'));

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

        if (! in_array($marginMode, ['ISOLATED', 'CROSS'], true)) {
            throw new DomainException('Margin mode must be Isolated or Cross.');
        }

        $entry = $this->positiveNumber($input['entry'] ?? null, 'Entry price');
        $stopLoss = $this->positiveNumber($input['stop_loss'] ?? null, 'Stop loss');
        $takeProfit = $this->positiveNumber($input['take_profit'] ?? null, 'Take profit');
        $leverage = $this->leverage($input['leverage'] ?? null, $market);

        $entryFeeRatePercent = $this->nonNegativeNumber($input['entry_fee_rate_percent'] ?? 0, 'Entry fee rate');
        $exitFeeRatePercent = $this->nonNegativeNumber($input['exit_fee_rate_percent'] ?? 0, 'Exit fee rate');
        $slippagePercent = $this->nonNegativeNumber($input['slippage_percent'] ?? 0, 'Slippage');
        $fundingRatePercent = $this->finiteNumber($input['funding_rate_percent'] ?? 0, 'Funding rate');
        $fundingIntervals = $this->nonNegativeInteger($input['funding_intervals'] ?? 0, 'Funding intervals');

        if ($entryFeeRatePercent > 100 || $exitFeeRatePercent > 100 || $slippagePercent > 100) {
            throw new DomainException('Fee and slippage percentages must be between 0% and 100%.');
        }

        if (abs($fundingRatePercent) > 100) {
            throw new DomainException('Funding rate must be between -100% and 100%.');
        }

        $maintenanceMarginRatePercent = $this->optionalPositiveNumber(
            $input['maintenance_margin_rate_percent'] ?? null,
            'Maintenance margin rate'
        );
        $maintenanceAmount = $this->optionalNonNegativeNumber(
            $input['maintenance_amount'] ?? null,
            'Maintenance amount'
        );
        $liquidationWalletBalance = $this->optionalPositiveNumber(
            $input['liquidation_wallet_balance'] ?? null,
            'Liquidation wallet balance'
        );

        if (($maintenanceMarginRatePercent === null) !== ($maintenanceAmount === null)) {
            throw new DomainException('Enter both Maintenance Margin Rate and Maintenance Amount to estimate liquidation price.');
        }

        if ($maintenanceMarginRatePercent !== null && $maintenanceMarginRatePercent >= 100) {
            throw new DomainException('Maintenance margin rate must be below 100%.');
        }

        $this->validatePriceStructure($side, $entry, $stopLoss, $takeProfit);

        $riskDistance = abs($entry - $stopLoss);
        $rewardDistance = abs($takeProfit - $entry);
        $riskRewardRatio = $rewardDistance / $riskDistance;
        $stopLossPercent = ($riskDistance / $entry) * 100;
        $takeProfitPercent = ($rewardDistance / $entry) * 100;

        $quantityStep = (float) ($instrument['lot_step'] ?? 0.00000001);
        $minimumQuantity = (float) ($instrument['min_lot'] ?? $quantityStep);
        $maximumQuantity = (float) ($instrument['max_lot'] ?? 1000000000.0);

        $entryFeeRate = $entryFeeRatePercent / 100;
        $exitFeeRate = $exitFeeRatePercent / 100;
        $slippageRate = $slippagePercent / 100;
        $fundingRate = $fundingRatePercent / 100;
        $sideSign = $side === 'BUY' ? 1.0 : -1.0;

        // Positive signed funding means an estimated payment/cost. Negative means a credit.
        // Risk-based sizing is conservative and never increases size because of expected funding credits.
        $fundingCostPerUnit = $entry * $fundingRate * $fundingIntervals * $sideSign;
        $conservativeFundingCostPerUnit = max(0.0, $fundingCostPerUnit);
        $allInStopRiskPerUnit = $riskDistance
            + ($entry * $entryFeeRate)
            + ($stopLoss * $exitFeeRate)
            + ($stopLoss * $slippageRate)
            + $conservativeFundingCostPerUnit;

        if ($allInStopRiskPerUnit <= 0) {
            throw new DomainException('Unable to calculate an all-in stop risk with the selected cost assumptions.');
        }

        $riskBudget = null;
        $marginBudget = null;

        if ($mode === 'RISK_BASED') {
            $riskBudget = $this->positiveNumber($input['risk_amount'] ?? null, 'Risk amount');
            $rawQuantity = $riskBudget / $allInStopRiskPerUnit;
        } else {
            $marginBudget = $this->positiveNumber($input['margin_amount'] ?? null, 'Margin amount');
            $rawNotional = $marginBudget * $leverage;
            $rawQuantity = $rawNotional / $entry;
        }

        $warnings = [
            'Binance Futures leverage and maintenance-margin tiers depend on the live symbol and position notional. Confirm the current Binance tier before placing a trade.',
            'Fee rates, funding rates and funding intervals can vary by account, symbol and time. Replace TradeX reference assumptions with the live Binance values before trading.',
            'USDT-denominated values are trading-risk estimates; verify current USDT/USD value if you need strict USD accounting.',
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

        $entryFee = $quantity !== null ? ($quantity * $entry) * $entryFeeRate : null;
        $stopExitFee = $quantity !== null ? ($quantity * $stopLoss) * $exitFeeRate : null;
        $targetExitFee = $quantity !== null ? ($quantity * $takeProfit) * $exitFeeRate : null;
        $stopSlippageCost = $quantity !== null ? ($quantity * $stopLoss) * $slippageRate : null;
        $targetSlippageCost = $quantity !== null ? ($quantity * $takeProfit) * $slippageRate : null;
        $fundingEstimate = $positionNotional !== null
            ? $positionNotional * $fundingRate * $fundingIntervals * $sideSign
            : null;
        $fundingRiskCost = $fundingEstimate !== null ? max(0.0, $fundingEstimate) : null;

        $estimatedStopCosts = $quantity !== null
            ? (float) $entryFee + (float) $stopExitFee + (float) $stopSlippageCost + (float) $fundingRiskCost
            : null;
        $estimatedTargetCosts = $quantity !== null
            ? (float) $entryFee + (float) $targetExitFee + (float) $targetSlippageCost + (float) $fundingEstimate
            : null;

        $allInStopRisk = $actualRisk !== null && $estimatedStopCosts !== null
            ? $actualRisk + $estimatedStopCosts
            : null;
        $netPotentialProfit = $potentialProfit !== null && $estimatedTargetCosts !== null
            ? $potentialProfit - $estimatedTargetCosts
            : null;
        $allInRiskRewardRatio = $allInStopRisk !== null && $allInStopRisk > 0 && $netPotentialProfit !== null
            ? $netPotentialProfit / $allInStopRisk
            : null;

        $riskUtilization = null;
        if ($mode === 'RISK_BASED' && $riskBudget !== null && $allInStopRisk !== null) {
            $riskUtilization = ($allInStopRisk / $riskBudget) * 100;
        } elseif ($mode === 'MARGIN_BASED' && $marginBudget !== null && $requiredInitialMargin !== null) {
            $riskUtilization = ($requiredInitialMargin / $marginBudget) * 100;
        }

        $slRoe = $requiredInitialMargin !== null && $requiredInitialMargin > 0 && $allInStopRisk !== null
            ? ($allInStopRisk / $requiredInitialMargin) * 100
            : null;

        $tpRoe = $requiredInitialMargin !== null && $requiredInitialMargin > 0 && $netPotentialProfit !== null
            ? ($netPotentialProfit / $requiredInitialMargin) * 100
            : null;

        $liquidation = $this->liquidationEstimate(
            $marginMode,
            $side,
            $entry,
            $stopLoss,
            $quantity,
            $requiredInitialMargin,
            $marginBudget,
            $liquidationWalletBalance,
            $maintenanceMarginRatePercent,
            $maintenanceAmount,
            $warnings
        );

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
            'minimum_lot_risk' => $minimumQuantity * $allInStopRiskPerUnit,
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
                'margin_mode' => $marginMode,
                'leverage' => $leverage,
                'position_quantity' => $quantity,
                'position_notional' => $positionNotional,
                'required_initial_margin' => $requiredInitialMargin,
                'risk_budget' => $riskBudget,
                'margin_budget' => $marginBudget,
                'stop_loss_percent' => $stopLossPercent,
                'take_profit_percent' => $takeProfitPercent,
                'gross_stop_risk' => $actualRisk,
                'gross_target_profit' => $potentialProfit,
                'all_in_stop_risk' => $allInStopRisk,
                'net_target_profit' => $netPotentialProfit,
                'all_in_risk_reward_ratio' => $allInRiskRewardRatio,
                'estimated_sl_roe_percent' => $slRoe,
                'estimated_tp_roe_percent' => $tpRoe,
                'costs' => [
                    'entry_fee_rate_percent' => $entryFeeRatePercent,
                    'exit_fee_rate_percent' => $exitFeeRatePercent,
                    'slippage_percent' => $slippagePercent,
                    'funding_rate_percent' => $fundingRatePercent,
                    'funding_intervals' => $fundingIntervals,
                    'entry_fee' => $entryFee,
                    'stop_exit_fee' => $stopExitFee,
                    'target_exit_fee' => $targetExitFee,
                    'stop_slippage' => $stopSlippageCost,
                    'target_slippage' => $targetSlippageCost,
                    'funding_estimate' => $fundingEstimate,
                    'funding_risk_cost' => $fundingRiskCost,
                    'estimated_stop_costs' => $estimatedStopCosts,
                    'estimated_target_costs' => $estimatedTargetCosts,
                ],
                'liquidation' => $liquidation,
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

    /**
     * @param array<int, string> $warnings
     * @return array<string, mixed>
     */
    private function liquidationEstimate(
        string $marginMode,
        string $side,
        float $entry,
        float $stopLoss,
        ?float $quantity,
        ?float $requiredInitialMargin,
        ?float $marginBudget,
        ?float $walletOverride,
        ?float $maintenanceMarginRatePercent,
        ?float $maintenanceAmount,
        array &$warnings
    ): array {
        $base = [
            'available' => false,
            'margin_mode' => $marginMode,
            'estimated_price' => null,
            'distance_from_entry_percent' => null,
            'stop_buffer_percent' => null,
            'stop_before_liquidation' => null,
            'wallet_balance_used' => null,
            'maintenance_margin_rate_percent' => $maintenanceMarginRatePercent,
            'maintenance_amount' => $maintenanceAmount,
            'live_tier_required' => true,
        ];

        if ($marginMode === 'CROSS') {
            $warnings[] = 'Cross-margin liquidation is not estimated from this trade alone because Binance requires cross wallet balance, other-position maintenance margin and unrealized PnL.';
            return $base;
        }

        if ($quantity === null || $requiredInitialMargin === null) {
            return $base;
        }

        if ($maintenanceMarginRatePercent === null || $maintenanceAmount === null) {
            $warnings[] = 'Isolated liquidation estimate is unavailable until you enter the current Binance Maintenance Margin Rate and Maintenance Amount for this position-notional tier.';
            return $base;
        }

        $walletBalance = $walletOverride
            ?? ($marginBudget !== null ? $marginBudget : $requiredInitialMargin);
        $sideSign = $side === 'BUY' ? 1.0 : -1.0;
        $mmr = $maintenanceMarginRatePercent / 100;
        $numerator = $walletBalance + $maintenanceAmount - ($sideSign * $quantity * $entry);
        $denominator = ($quantity * $mmr) - ($sideSign * $quantity);

        if (abs($denominator) < 1.0E-15) {
            $warnings[] = 'Unable to estimate liquidation because the selected maintenance-margin inputs produce an invalid denominator.';
            return $base;
        }

        $liquidationPrice = $numerator / $denominator;

        if (! is_finite($liquidationPrice) || $liquidationPrice <= 0) {
            $warnings[] = 'The supplied maintenance-margin inputs do not produce a valid positive isolated liquidation price. Re-check the live Binance tier values.';
            return $base;
        }

        $distancePercent = (abs($entry - $liquidationPrice) / $entry) * 100;
        $stopBufferPercent = $side === 'BUY'
            ? (($stopLoss - $liquidationPrice) / $entry) * 100
            : (($liquidationPrice - $stopLoss) / $entry) * 100;
        $stopBeforeLiquidation = $side === 'BUY'
            ? $stopLoss > $liquidationPrice
            : $stopLoss < $liquidationPrice;

        if (! $stopBeforeLiquidation) {
            $warnings[] = 'Estimated liquidation is reached before or at the planned Stop Loss. Reduce leverage/position size, add isolated margin, or move the risk plan before trading.';
        }

        return [
            'available' => true,
            'margin_mode' => $marginMode,
            'estimated_price' => $liquidationPrice,
            'distance_from_entry_percent' => $distancePercent,
            'stop_buffer_percent' => $stopBufferPercent,
            'stop_before_liquidation' => $stopBeforeLiquidation,
            'wallet_balance_used' => $walletBalance,
            'maintenance_margin_rate_percent' => $maintenanceMarginRatePercent,
            'maintenance_amount' => $maintenanceAmount,
            'live_tier_required' => true,
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

    private function nonNegativeNumber(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new DomainException($label.' must be a valid number.');
        }

        $number = (float) $value;

        if (! is_finite($number) || $number < 0) {
            throw new DomainException($label.' must be zero or greater.');
        }

        return $number;
    }

    private function finiteNumber(mixed $value, string $label): float
    {
        if (! is_numeric($value)) {
            throw new DomainException($label.' must be a valid number.');
        }

        $number = (float) $value;

        if (! is_finite($number)) {
            throw new DomainException($label.' must be a finite number.');
        }

        return $number;
    }

    private function nonNegativeInteger(mixed $value, string $label): int
    {
        if (! is_numeric($value)) {
            throw new DomainException($label.' must be a whole number.');
        }

        $number = (float) $value;
        $integer = (int) $number;

        if (! is_finite($number) || $number !== (float) $integer || $integer < 0) {
            throw new DomainException($label.' must be a whole number zero or greater.');
        }

        return $integer;
    }

    private function optionalPositiveNumber(mixed $value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->positiveNumber($value, $label);
    }

    private function optionalNonNegativeNumber(mixed $value, string $label): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->nonNegativeNumber($value, $label);
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
