<?php

namespace App\Services\Admin\Investments;

use App\Models\Investment;
use App\Models\InvestmentProfitPeriod;
use Carbon\CarbonImmutable;
use DomainException;

class InvestmentPeriodResolver
{
    public function forTimestamp(
        Investment $investment,
        string $timestamp
    ): InvestmentProfitPeriod {
        $date = CarbonImmutable::parse($timestamp)->toDateString();

        $period = $investment->profitPeriods()
            ->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>', $date)
            ->orderBy('sequence')
            ->first();

        if (! $period) {
            $period = $investment->profitPeriods()
                ->whereDate('period_end', $date)
                ->orderByDesc('sequence')
                ->first();
        }

        if (! $period) {
            throw new DomainException(
                'The transaction date is outside the generated investment profit-review schedule.'
            );
        }

        return $period;
    }
}
