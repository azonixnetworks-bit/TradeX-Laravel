<?php

namespace App\Services\Support;

use Carbon\CarbonImmutable;

class SupportSlaClock
{
    public function addBusinessMinutes(CarbonImmutable $from, int $minutes, string $timezone, array $businessHours = []): CarbonImmutable
    {
        $cursor = $from->setTimezone($timezone);
        $remaining = max(0, $minutes);
        $weekdays = array_values(array_filter(
            array_map('intval', (array) ($businessHours['weekdays'] ?? [1, 2, 3, 4, 5])),
            fn (int $day): bool => $day >= 1 && $day <= 7
        ));
        $weekdays = $weekdays === [] ? [1, 2, 3, 4, 5] : array_unique($weekdays);
        [$openHour, $openMinute] = $this->timeParts((string) ($businessHours['opens'] ?? '09:00'), [9, 0]);
        [$closeHour, $closeMinute] = $this->timeParts((string) ($businessHours['closes'] ?? '17:00'), [17, 0]);

        while ($remaining > 0) {
            if (! in_array($cursor->dayOfWeekIso, $weekdays, true)) {
                $cursor = $cursor->addDay()->setTime($openHour, $openMinute);

                continue;
            }

            $opensAt = $cursor->setTime($openHour, $openMinute);
            $closesAt = $cursor->setTime($closeHour, $closeMinute);
            if ($closesAt->lessThanOrEqualTo($opensAt)) {
                [$openHour, $openMinute, $closeHour, $closeMinute] = [9, 0, 17, 0];
                $opensAt = $cursor->setTime($openHour, $openMinute);
                $closesAt = $cursor->setTime($closeHour, $closeMinute);
            }

            if ($cursor->lessThan($opensAt)) {
                $cursor = $opensAt;
            }

            if ($cursor->greaterThanOrEqualTo($closesAt)) {
                $cursor = $cursor->addDay()->setTime($openHour, $openMinute);

                continue;
            }

            $available = (int) $cursor->diffInMinutes($closesAt);
            $step = min($remaining, (int) $available);
            if ($step < 1) {
                $cursor = $cursor->addDay()->setTime($openHour, $openMinute);

                continue;
            }
            $cursor = $cursor->addMinutes($step);
            $remaining -= $step;

            if ($remaining > 0) {
                $cursor = $cursor->addDay()->setTime($openHour, $openMinute);
            }
        }

        return $cursor->utc();
    }

    /** @return array{0: int, 1: int} */
    private function timeParts(string $value, array $fallback): array
    {
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return $fallback;
        }

        return array_map('intval', explode(':', $value));
    }
}
