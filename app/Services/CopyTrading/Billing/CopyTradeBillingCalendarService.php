<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\CopyTradeBillingCalendarDay;
use App\Models\CopyTradeBrokerAccount;
use DomainException;
use Carbon\CarbonImmutable;

class CopyTradeBillingCalendarService
{
    public const BASIS_FOREX_OFFICIAL =
        'FOREX_OFFICIAL_TRADING_DAYS';

    public const BASIS_UTC_MONTH =
        'UTC_CALENDAR_MONTH';

    public function periodFor(
        CopyTradeBrokerAccount $account,
        CarbonImmutable $at
    ): array {
        $account->loadMissing('brokerMarket');

        $market = strtoupper(
            (string) $account->brokerMarket?->market_category
        );

        if ($market === 'FOREX') {
            $timezone = $this->forexTimezone();

            $local = $at->setTimezone($timezone);
            $startLocal = $local->startOfMonth();
            $endLocal = $startLocal->addMonth();

            return [
                'basis' => self::BASIS_FOREX_OFFICIAL,
                'period_start' => $startLocal->utc(),
                'period_end' => $endLocal->utc(),
                'timezone' => $timezone,
            ];
        }

        if (in_array($market, ['DERIV', 'CRYPTO'], true)) {
            $utc = $at->utc();
            $start = $utc->startOfMonth();

            return [
                'basis' => self::BASIS_UTC_MONTH,
                'period_start' => $start,
                'period_end' => $start->addMonth(),
                'timezone' => 'UTC',
            ];
        }

        throw new DomainException(
            'Unsupported Copy Trade billing market category.'
        );
    }

    public function ratio(
        CopyTradeBrokerAccount $account,
        CarbonImmutable $eventAt,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd
    ): array {
        if ($periodEnd->lte($periodStart)) {
            throw new DomainException(
                'Invalid Copy Trade billing period.'
            );
        }

        if ($eventAt->gte($periodEnd)) {
            return [
                'numerator' => 0,
                'denominator' => 1,
                'formula_code' => 'NO_REMAINING_PERIOD',
            ];
        }

        $account->loadMissing('brokerMarket');

        $market = strtoupper(
            (string) $account->brokerMarket?->market_category
        );

        if ($market === 'FOREX') {
            return $this->forexRatio(
                $eventAt,
                $periodStart,
                $periodEnd
            );
        }

        if (! in_array($market, ['DERIV', 'CRYPTO'], true)) {
            throw new DomainException(
                'Unsupported Copy Trade billing market category.'
            );
        }

        $total = max(
            1,
            (int) $periodStart->diffInSeconds(
                $periodEnd,
                true
            )
        );

        $effective = $eventAt->lt($periodStart)
            ? $periodStart
            : $eventAt;

        $remaining = max(
            0,
            (int) $effective->diffInSeconds(
                $periodEnd,
                true
            )
        );

        return [
            'numerator' => $remaining,
            'denominator' => $total,
            'formula_code' => 'UTC_CALENDAR_PORTION',
        ];
    }

    public function lockForexCalendar(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd
    ): void {
        $timezone = $this->forexTimezone();

        [$firstDate, $lastDate] = $this->localDateRange(
            $periodStart,
            $periodEnd,
            $timezone
        );

        $this->assertCompleteForexCalendar(
            $firstDate,
            $lastDate,
            $timezone
        );

        CopyTradeBillingCalendarDay::query()
            ->where('market_category', 'FOREX')
            ->whereDate(
                'calendar_date',
                '>=',
                $firstDate
            )
            ->whereDate(
                'calendar_date',
                '<=',
                $lastDate
            )
            ->whereNull('locked_at')
            ->get()
            ->each(function (
                CopyTradeBillingCalendarDay $day
            ): void {
                $day->forceFill([
                    'locked_at' => now(),
                ])->save();
            });
    }

    private function forexRatio(
        CarbonImmutable $eventAt,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd
    ): array {
        $timezone = $this->forexTimezone();

        [$firstDate, $lastDate] = $this->localDateRange(
            $periodStart,
            $periodEnd,
            $timezone
        );

        $this->assertCompleteForexCalendar(
            $firstDate,
            $lastDate,
            $timezone
        );

        $total = CopyTradeBillingCalendarDay::query()
            ->where('market_category', 'FOREX')
            ->whereDate(
                'calendar_date',
                '>=',
                $firstDate
            )
            ->whereDate(
                'calendar_date',
                '<=',
                $lastDate
            )
            ->where('is_billable_day', true)
            ->count();

        if ($total <= 0) {
            throw new DomainException(
                'Forex official billing calendar contains no trading days for this period.'
            );
        }

        $eventLocalDate = $eventAt
            ->setTimezone($timezone)
            ->toDateString();

        $remainingStart =
            strcmp($eventLocalDate, $firstDate) < 0
                ? $firstDate
                : $eventLocalDate;

        $remaining = CopyTradeBillingCalendarDay::query()
            ->where('market_category', 'FOREX')
            ->whereDate(
                'calendar_date',
                '>=',
                $remainingStart
            )
            ->whereDate(
                'calendar_date',
                '<=',
                $lastDate
            )
            ->where('is_billable_day', true)
            ->count();

        return [
            'numerator' => $remaining,
            'denominator' => $total,
            'formula_code' =>
                'FOREX_OFFICIAL_TRADING_DAYS',
        ];
    }

    private function assertCompleteForexCalendar(
        string $firstDate,
        string $lastDate,
        string $timezone
    ): void {
        $expectedCalendarDays =
            (int) CarbonImmutable::parse(
                $firstDate,
                $timezone
            )->diffInDays(
                CarbonImmutable::parse(
                    $lastDate,
                    $timezone
                )->addDay()
            );

        $rows = CopyTradeBillingCalendarDay::query()
            ->where('market_category', 'FOREX')
            ->whereDate(
                'calendar_date',
                '>=',
                $firstDate
            )
            ->whereDate(
                'calendar_date',
                '<=',
                $lastDate
            )
            ->count();

        if ($rows !== $expectedCalendarDays) {
            throw new DomainException(
                'Forex official billing calendar is incomplete for this period.'
            );
        }
    }

    private function localDateRange(
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
        string $timezone
    ): array {
        $firstDate = $periodStart
            ->setTimezone($timezone)
            ->toDateString();

        $lastDate = $periodEnd
            ->setTimezone($timezone)
            ->subDay()
            ->toDateString();

        return [$firstDate, $lastDate];
    }

    private function forexTimezone(): string
    {
        $timezone = trim(
            (string) config(
                'tradex_copy_trading.billing.forex_server_timezone',
                ''
            )
        );

        if ($timezone === '') {
            throw new DomainException(
                'Forex billing server timezone is not configured.'
            );
        }

        return $timezone;
    }
}
