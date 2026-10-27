<?php

namespace App\Services\Support;

use Carbon\CarbonImmutable;

class SupportSlaClock
{
    public function addBusinessMinutes(CarbonImmutable $from, int $minutes, string $timezone): CarbonImmutable
    {
        $cursor = $from->setTimezone($timezone);
        $remaining = max(0, $minutes);

        while ($remaining > 0) {
            if ($cursor->isWeekend()) {
                $cursor = $cursor->next('Monday')->setTime(9, 0);

                continue;
            }

            if ($cursor->hour < 9) {
                $cursor = $cursor->setTime(9, 0);
            }

            if ($cursor->hour >= 17) {
                $cursor = $cursor->addDay()->setTime(9, 0);

                continue;
            }

            $available = $cursor->diffInMinutes($cursor->setTime(17, 0));
            $step = min($remaining, (int) $available);
            $cursor = $cursor->addMinutes($step);
            $remaining -= $step;

            if ($remaining > 0) {
                $cursor = $cursor->addDay()->setTime(9, 0);
            }
        }

        return $cursor->utc();
    }
}
