<?php

namespace App\Services\Trading;

use DomainException;

class DividendCalculatorService
{
    /** @param array<string, mixed> $input */
    public function calculate(array $input): array
    {
        $shares = $this->positive($input['shares'] ?? null, 'Shares');
        $price = $this->positive($input['share_price'] ?? null, 'Share price');
        $dps = $this->positive($input['dividend_per_share'] ?? null, 'Dividend per share');
        $frequency = (int) ($input['payments_per_year'] ?? 0);
        $withholding = $this->nonNegative($input['withholding_tax_percent'] ?? 0, 'Withholding tax');
        $fees = $this->nonNegative($input['fees_per_payment'] ?? 0, 'Fees per payment');
        $assumption = (bool) ($input['repeated_payment_assumption'] ?? false);

        if (! in_array($frequency, [1, 2, 4, 12], true)) {
            throw new DomainException('Payments per year must be 1, 2, 4 or 12.');
        }
        if ($withholding > 100) {
            throw new DomainException('Withholding tax must be between 0% and 100%.');
        }

        $grossPerPayment = $shares * $dps;
        $annualMultiplier = $assumption ? $frequency : 1;
        $annualGross = $grossPerPayment * $annualMultiplier;
        $tax = $annualGross * ($withholding / 100);
        $annualFees = $fees * $annualMultiplier;
        $net = max(0.0, $annualGross - $tax - $annualFees);
        $annualDps = $dps * $annualMultiplier;

        return [
            'symbol' => strtoupper(trim((string) ($input['symbol'] ?? 'MANUAL'))),
            'company_name' => trim((string) ($input['company_name'] ?? 'Manual stock')),
            'currency' => strtoupper(trim((string) ($input['currency'] ?? 'USD'))),
            'shares' => $shares,
            'share_price' => $price,
            'dividend_per_share' => $dps,
            'payments_per_year' => $frequency,
            'gross_per_payment' => $grossPerPayment,
            'projected_annual_gross' => $annualGross,
            'projected_annual_net' => $net,
            'annual_dividend_per_share' => $annualDps,
            'dividend_yield_percent' => ($annualDps / $price) * 100,
            'investment_value' => $shares * $price,
            'withholding_tax_percent' => $withholding,
            'estimated_tax' => $tax,
            'estimated_fees' => $annualFees,
            'status' => 'MANUAL_ESTIMATE',
            'repeated_payment_assumption' => $assumption,
            'warnings' => [
                $assumption
                    ? 'Annual projection assumes the entered dividend repeats at the selected frequency.'
                    : 'Only the entered payment is included. No future dividend declaration is assumed.',
                'Tax and fees are user-provided estimates, not tax advice.',
            ],
        ];
    }

    private function positive(mixed $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
            throw new DomainException($label.' must be greater than zero.');
        }

        return (float) $value;
    }

    private function nonNegative(mixed $value, string $label): float
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            throw new DomainException($label.' must be zero or greater.');
        }

        return (float) $value;
    }
}
