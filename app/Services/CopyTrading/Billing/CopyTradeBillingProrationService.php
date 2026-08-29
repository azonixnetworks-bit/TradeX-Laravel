<?php

namespace App\Services\CopyTrading\Billing;

use DomainException;

class CopyTradeBillingProrationService
{
    public function prorate(
        int $amountMinor,
        int $numerator,
        int $denominator
    ): int {
        if (
            $amountMinor < 0
            || $numerator < 0
            || $denominator <= 0
            || $numerator > $denominator
        ) {
            throw new DomainException(
                'Invalid Copy Trade billing proration inputs.'
            );
        }

        if ($amountMinor === 0 || $numerator === 0) {
            return 0;
        }

        /*
         * Positive integer commercial half-up rounding:
         * calculate using the exact rational amount and round once
         * to the final USD-cent invoice amount.
         */
        return intdiv(
            ($amountMinor * $numerator)
                + intdiv($denominator, 2),
            $denominator
        );
    }

    public function upgradeCharge(
        int $newFullFeeMinor,
        int $currentFullFeeMinor,
        int $numerator,
        int $denominator
    ): int {
        $difference = max(
            0,
            $newFullFeeMinor - $currentFullFeeMinor
        );

        return $this->prorate(
            $difference,
            $numerator,
            $denominator
        );
    }
}
