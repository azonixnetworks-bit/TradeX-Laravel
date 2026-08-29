<?php

namespace App\Services\CopyTrading\Billing;

use DomainException;

class CopyTradeBillingPricingService
{
    public function tierForBalance(int $balanceMinor): ?array
    {
        if ($balanceMinor < 0) {
            throw new DomainException(
                'Verified Billable Balance cannot be negative.'
            );
        }

        foreach (
            (array) config(
                'tradex_copy_trading.billing.pricing_tiers',
                []
            ) as $tier
        ) {
            $min = (int) ($tier['min_balance_minor'] ?? 0);
            $max = $tier['max_balance_minor'] ?? null;

            if (
                $balanceMinor >= $min
                && (
                    $max === null
                    || $balanceMinor < (int) $max
                )
            ) {
                return [
                    'code' => (string) $tier['code'],
                    'min_balance_minor' => $min,
                    'max_balance_minor' =>
                        $max === null ? null : (int) $max,
                    'full_monthly_fee_minor' =>
                        (int) $tier['full_monthly_fee_minor'],
                ];
            }
        }

        return null;
    }

    public function requireEligibleTier(int $balanceMinor): array
    {
        $minimum = (int) config(
            'tradex_copy_trading.activation.minimum_verified_billable_balance_minor',
            25000
        );

        if ($balanceMinor < $minimum) {
            throw new DomainException(
                'Copy Trade billing requires at least USD 250 Verified Billable Balance.'
            );
        }

        $tier = $this->tierForBalance($balanceMinor);

        if ($tier === null) {
            throw new DomainException(
                'No Copy Trade pricing tier is configured for this balance.'
            );
        }

        return $tier;
    }
}
