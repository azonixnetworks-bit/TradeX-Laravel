<?php

namespace App\Services\MarketClock;

use Carbon\CarbonImmutable;
use DateTimeZone;
use InvalidArgumentException;

class MarketClockService
{
    public function snapshot(
        ?string $displayTimezone = null,
        ?CarbonImmutable $nowUtc = null
    ): array {
        $displayTimezone = $this->normalizeTimezone(
            $displayTimezone
                ?: (string) config('market_clock.default_timezone', 'UTC')
        );

        $nowUtc = ($nowUtc ?: CarbonImmutable::now('UTC'))
            ->setTimezone('UTC');

        $sessions = [];

        foreach ((array) config('market_clock.sessions', []) as $index => $definition) {
            $sessions[] = $this->buildSession(
                (array) $definition,
                (int) $index,
                $displayTimezone,
                $nowUtc
            );
        }

        $activeSessions = array_values(array_filter(
            $sessions,
            static fn (array $session): bool => $session['status'] === 'open'
        ));

        $nextSession = $this->nextSession($sessions, $nowUtc);
        $activeNames = array_column($activeSessions, 'name');

        return [
            'server_time_utc' => $nowUtc->toIso8601String(),
            'display_timezone' => $displayTimezone,
            'display_time' => $nowUtc
                ->setTimezone($displayTimezone)
                ->format('H:i:s'),
            'display_date' => $nowUtc
                ->setTimezone($displayTimezone)
                ->format('D, d M Y'),
            'display_offset' => $nowUtc
                ->setTimezone($displayTimezone)
                ->format('P'),
            'market_status' => count($activeSessions) > 0 ? 'open' : 'between_sessions',
            'active_sessions' => array_map(
                static fn (array $session): array => [
                    'key' => $session['key'],
                    'name' => $session['name'],
                    'region' => $session['region'],
                    'closes_at_utc' => $session['close_at_utc'],
                    'closes_at_display' => $session['close_at_display'],
                ],
                $activeSessions
            ),
            'next_session' => $nextSession,
            'summary' => [
                'total_sessions' => count($sessions),
                'open_now' => count($activeSessions),
                'closed_now' => count($sessions) - count($activeSessions),
                'current_overlap' => count($activeNames) >= 2
                    ? implode(' / ', array_slice($activeNames, 0, 4))
                    : null,
                'insight' => $this->insight($activeNames),
            ],
            'sessions' => $sessions,
        ];
    }

    public function normalizeTimezone(?string $timezone): string
    {
        $timezone = trim((string) $timezone);

        if ($timezone === '') {
            return 'UTC';
        }

        if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidArgumentException('Invalid IANA timezone.');
        }

        return $timezone;
    }

    public function availableTimezones(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    private function buildSession(
        array $definition,
        int $index,
        string $displayTimezone,
        CarbonImmutable $nowUtc
    ): array {
        $timezone = $this->normalizeTimezone(
            (string) ($definition['timezone'] ?? 'UTC')
        );

        $localNow = $nowUtc->setTimezone($timezone);
        $days = array_map('intval', (array) ($definition['days'] ?? [1, 2, 3, 4, 5]));
        $openTime = (string) ($definition['open'] ?? '09:00');
        $closeTime = (string) ($definition['close'] ?? '17:00');

        $currentWindow = $this->currentWindow(
            $localNow,
            $days,
            $openTime,
            $closeTime
        );

        $isOpen = $currentWindow !== null;

        $nextWindow = $this->nextWindow(
            $localNow,
            $days,
            $openTime,
            $closeTime,
            $isOpen ? $currentWindow['close'] : null
        );

        $dialWindow = $currentWindow ?: $nextWindow;
        $dialSegments = $this->dialSegments(
            $dialWindow['open']->setTimezone($displayTimezone),
            $dialWindow['close']->setTimezone($displayTimezone)
        );

        $activeClose = $isOpen ? $currentWindow['close'] : null;

        return [
            'key' => (string) ($definition['key'] ?? 'session_'.$index),
            'name' => (string) ($definition['name'] ?? 'Session '.($index + 1)),
            'region' => (string) ($definition['region'] ?? 'GLOBAL'),
            'timezone' => $timezone,
            'status' => $isOpen ? 'open' : 'closed',
            'local_time' => $localNow->format('H:i:s'),
            'local_date' => $localNow->format('D, d M Y'),
            'local_offset' => $localNow->format('P'),
            'configured_open_local' => $openTime,
            'configured_close_local' => $closeTime,
            'open_at_utc' => $isOpen
                ? $currentWindow['open']->setTimezone('UTC')->toIso8601String()
                : null,
            'close_at_utc' => $activeClose
                ? $activeClose->setTimezone('UTC')->toIso8601String()
                : null,
            'close_at_display' => $activeClose
                ? $activeClose->setTimezone($displayTimezone)->format('D H:i')
                : null,
            'next_open_at_utc' => $nextWindow['open']->setTimezone('UTC')->toIso8601String(),
            'next_open_at_display' => $nextWindow['open']->setTimezone($displayTimezone)->format('D H:i'),
            'next_close_at_utc' => $nextWindow['close']->setTimezone('UTC')->toIso8601String(),
            'next_close_at_display' => $nextWindow['close']->setTimezone($displayTimezone)->format('D H:i'),
            'dial_track' => $index,
            'dial_segments' => $dialSegments,
        ];
    }

    private function currentWindow(
        CarbonImmutable $localNow,
        array $days,
        string $openTime,
        string $closeTime
    ): ?array {
        foreach ([-1, 0] as $dayOffset) {
            $date = $localNow->startOfDay()->addDays($dayOffset);

            if (! in_array($date->isoWeekday(), $days, true)) {
                continue;
            }

            $window = $this->windowForDate($date, $openTime, $closeTime);

            if ($localNow->greaterThanOrEqualTo($window['open'])
                && $localNow->lessThan($window['close'])) {
                return $window;
            }
        }

        return null;
    }

    private function nextWindow(
        CarbonImmutable $localNow,
        array $days,
        string $openTime,
        string $closeTime,
        ?CarbonImmutable $after = null
    ): array {
        $threshold = $after ?: $localNow;
        $baseDate = $localNow->startOfDay();

        for ($dayOffset = 0; $dayOffset <= 14; $dayOffset++) {
            $date = $baseDate->addDays($dayOffset);

            if (! in_array($date->isoWeekday(), $days, true)) {
                continue;
            }

            $window = $this->windowForDate($date, $openTime, $closeTime);

            if ($window['open']->greaterThan($threshold)) {
                return $window;
            }
        }

        throw new InvalidArgumentException('Unable to resolve the next market session window.');
    }

    private function windowForDate(
        CarbonImmutable $date,
        string $openTime,
        string $closeTime
    ): array {
        $open = $this->withTime($date, $openTime);
        $close = $this->withTime($date, $closeTime);

        if ($close->lessThanOrEqualTo($open)) {
            $close = $close->addDay();
        }

        return [
            'open' => $open,
            'close' => $close,
        ];
    }

    private function withTime(CarbonImmutable $date, string $time): CarbonImmutable
    {
        if (! preg_match('/^(\d{2}):(\d{2})$/', $time, $matches)) {
            throw new InvalidArgumentException('Market session time must use HH:MM format.');
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException('Market session time is outside the valid clock range.');
        }

        return $date->setTime($hour, $minute, 0);
    }

    private function dialSegments(
        CarbonImmutable $displayOpen,
        CarbonImmutable $displayClose
    ): array {
        $startMinute = ($displayOpen->hour * 60) + $displayOpen->minute;
        $durationMinutes = max(
            1,
            (int) round($displayOpen->diffInSeconds($displayClose) / 60)
        );

        if ($durationMinutes >= 1440) {
            return [[
                'start_minute' => 0,
                'end_minute' => 1440,
            ]];
        }

        $endUnwrapped = $startMinute + $durationMinutes;

        if ($endUnwrapped <= 1440) {
            return [[
                'start_minute' => $startMinute,
                'end_minute' => $endUnwrapped,
            ]];
        }

        return [
            [
                'start_minute' => $startMinute,
                'end_minute' => 1440,
            ],
            [
                'start_minute' => 0,
                'end_minute' => $endUnwrapped - 1440,
            ],
        ];
    }

    private function nextSession(array $sessions, CarbonImmutable $nowUtc): ?array
    {
        $candidate = null;

        foreach ($sessions as $session) {
            $nextOpen = CarbonImmutable::parse(
                $session['next_open_at_utc'],
                'UTC'
            );

            if ($candidate === null || $nextOpen->lessThan($candidate['time'])) {
                $candidate = [
                    'time' => $nextOpen,
                    'session' => $session,
                ];
            }
        }

        if ($candidate === null) {
            return null;
        }

        $seconds = max(
            0,
            (int) floor($nowUtc->diffInSeconds($candidate['time'], false))
        );

        return [
            'key' => $candidate['session']['key'],
            'name' => $candidate['session']['name'],
            'region' => $candidate['session']['region'],
            'opens_at_utc' => $candidate['session']['next_open_at_utc'],
            'opens_at_display' => $candidate['session']['next_open_at_display'],
            'opens_in_seconds' => $seconds,
        ];
    }

    private function insight(array $activeNames): string
    {
        if (count($activeNames) === 0) {
            return 'Configured global trading centres are currently between active session windows.';
        }

        if (count($activeNames) === 1) {
            return $activeNames[0].' is currently inside its configured trading-centre session.';
        }

        return 'Multiple trading centres are active: '
            .implode(', ', array_slice($activeNames, 0, 4))
            .'. Session overlaps can correspond with higher market participation.';
    }
}
