<?php

namespace App\Support\Reporting;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ReportDateRange
{
    public function resolve(
        ?string $from,
        ?string $to,
        string $businessTimezone
    ): array {
        $today = CarbonImmutable::now($businessTimezone)
            ->toDateString();

        $from = $this->normalizeDate($from, $today, 'from');
        $to = $this->normalizeDate($to, $from, 'to');

        $fromLocal = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $from.' 00:00:00',
            $businessTimezone
        );

        $toLocal = CarbonImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $to.' 00:00:00',
            $businessTimezone
        );

        if ($toLocal->lessThan($fromLocal)) {
            throw ValidationException::withMessages([
                'to' => 'The report end date must be on or after the start date.',
            ]);
        }

        $inclusiveDays =
            (int) $fromLocal->diffInDays($toLocal)
            + 1;

        $maxDays = max(
            1,
            (int) config('tradex_reporting.max_range_days', 366)
        );

        if ($inclusiveDays > $maxDays) {
            throw ValidationException::withMessages([
                'to' => 'The report range may not exceed '.$maxDays.' days.',
            ]);
        }

        $databaseTimezone = (string) config(
            'app.timezone',
            'UTC'
        );

        return [
            'from' => $from,
            'to' => $to,
            'days' => $inclusiveDays,
            'timezone' => $businessTimezone,
            'start' => $fromLocal
                ->startOfDay()
                ->timezone($databaseTimezone),
            'end' => $toLocal
                ->endOfDay()
                ->timezone($databaseTimezone),
        ];
    }

    private function normalizeDate(
        ?string $value,
        string $default,
        string $field
    ): string {
        $value = trim((string) $value);

        if ($value === '') {
            return $default;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat(
                '!Y-m-d',
                $value,
                'UTC'
            );
        } catch (Throwable) {
            $parsed = null;
        }

        if (
            ! $parsed instanceof CarbonImmutable
            || $parsed->format('Y-m-d') !== $value
        ) {
            throw ValidationException::withMessages([
                $field => 'The '.$field.' date must use YYYY-MM-DD format.',
            ]);
        }

        return $value;
    }
}
