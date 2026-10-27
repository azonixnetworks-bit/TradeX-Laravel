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
}
