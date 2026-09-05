<?php

namespace Tests\Unit\MarketClock;

use App\Services\MarketClock\MarketClockService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class MarketClockServiceTest extends TestCase
{
    public function test_london_session_uses_iana_dst_rules_instead_of_fixed_offsets(): void
    {
        config()->set('market_clock.sessions', [$this->londonSession()]);

        $service = app(MarketClockService::class);

        $winter = $service->snapshot(
            'UTC',
            CarbonImmutable::parse('2026-01-12 08:30:00', 'UTC')
        );

        $summer = $service->snapshot(
            'UTC',
            CarbonImmutable::parse('2026-07-13 07:30:00', 'UTC')
        );

        $this->assertSame('open', $winter['sessions'][0]['status']);
        $this->assertSame(
            '2026-01-12T08:00:00+00:00',
            $winter['sessions'][0]['open_at_utc']
        );

        $this->assertSame('open', $summer['sessions'][0]['status']);
        $this->assertSame(
            '2026-07-13T07:00:00+00:00',
            $summer['sessions'][0]['open_at_utc']
        );
    }

    public function test_session_dial_is_converted_to_selected_display_timezone(): void
    {
        config()->set('market_clock.sessions', [$this->londonSession()]);

        $snapshot = app(MarketClockService::class)->snapshot(
            'Asia/Colombo',
            CarbonImmutable::parse('2026-07-13 07:30:00', 'UTC')
        );

        $this->assertSame('Asia/Colombo', $snapshot['display_timezone']);
        $this->assertSame('+05:30', $snapshot['display_offset']);
        $this->assertSame(
            [[
                'start_minute' => 750,
                'end_minute' => 1290,
            ]],
            $snapshot['sessions'][0]['dial_segments']
        );
    }

    public function test_next_session_skips_the_weekend(): void
    {
        config()->set('market_clock.sessions', [$this->londonSession()]);

        $snapshot = app(MarketClockService::class)->snapshot(
            'UTC',
            CarbonImmutable::parse('2026-09-04 20:00:00', 'UTC')
        );

        $this->assertSame('closed', $snapshot['sessions'][0]['status']);
        $this->assertSame(
            '2026-09-07T07:00:00+00:00',
            $snapshot['next_session']['opens_at_utc']
        );
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(MarketClockService::class)->normalizeTimezone(
            'UTC+5:30'
        );
    }

    private function londonSession(): array
    {
        return [
            'key' => 'london',
            'name' => 'London',
            'region' => 'EUROPE',
            'timezone' => 'Europe/London',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ];
    }
}
