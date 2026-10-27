<?php

namespace Tests\Unit\Support;

use App\Services\Support\SupportSlaClock;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SupportSlaClockTest extends TestCase
{
    public function test_business_minutes_skip_nights_and_weekends(): void
    {
        $clock = new SupportSlaClock;
        $friday = CarbonImmutable::parse('2026-10-30 16:30:00', 'Asia/Colombo')->utc();
        $due = $clock->addBusinessMinutes($friday, 60, 'Asia/Colombo')->setTimezone('Asia/Colombo');
        $this->assertSame('2026-11-02 09:30:00', $due->format('Y-m-d H:i:s'));
    }

    public function test_department_business_hours_and_working_days_are_used(): void
    {
        $clock = new SupportSlaClock;
        $friday = CarbonImmutable::parse('2026-10-30 11:30:00', 'Asia/Colombo')->utc();
        $due = $clock->addBusinessMinutes($friday, 90, 'Asia/Colombo', [
            'weekdays' => [1, 2, 3, 4, 5, 6],
            'opens' => '10:00',
            'closes' => '12:00',
        ])->setTimezone('Asia/Colombo');

        $this->assertSame('2026-10-31 11:00:00', $due->format('Y-m-d H:i:s'));
    }
}
