<?php

namespace Tests\Unit\Reporting;

use App\Support\Reporting\ReportDateRange;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportDateRangeTest extends TestCase
{
    public function test_custom_range_is_inclusive_and_converted_to_database_timezone(): void
    {
        config()->set('app.timezone', 'UTC');
        config()->set('tradex_reporting.max_range_days', 366);

        $range = app(ReportDateRange::class)->resolve(
            '2026-08-25',
            '2026-08-27',
            'Asia/Colombo'
        );

        $this->assertSame('2026-08-25', $range['from']);
        $this->assertSame('2026-08-27', $range['to']);
        $this->assertSame(3, $range['days']);
        $this->assertSame(
            '2026-08-24 18:30:00',
            $range['start']->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-08-27 18:29:59',
            $range['end']->format('Y-m-d H:i:s')
        );
    }

    public function test_range_longer_than_configured_maximum_is_rejected(): void
    {
        config()->set('tradex_reporting.max_range_days', 30);

        $this->expectException(ValidationException::class);

        app(ReportDateRange::class)->resolve(
            '2026-01-01',
            '2026-02-01',
            'Asia/Colombo'
        );
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        app(ReportDateRange::class)->resolve(
            '2026-08-30',
            '2026-08-29',
            'Asia/Colombo'
        );
    }
}
