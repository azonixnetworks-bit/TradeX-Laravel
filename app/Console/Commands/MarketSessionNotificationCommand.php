<?php

namespace App\Console\Commands;

use App\Models\NotificationMessage;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Console\Command;

class MarketSessionNotificationCommand extends Command
{
    protected $signature = 'tradex:notifications:market-sessions';

    protected $description =
        'Create due TradeX market-session reminder notification events.';

    public function handle(NotificationAutomationPublisher $publisher): int
    {
        if (
            ! (bool) config('tradex_notification_automation.enabled', false)
            || ! (bool) config(
                'tradex_notification_automation.events.market_sessions',
                true
            )
        ) {
            $this->components->info('TradeX market-session notifications are disabled.');

            return self::SUCCESS;
        }

        $nowUtc = CarbonImmutable::now('UTC');
        $windowMinutes = min(
            10,
            max(
                1,
                (int) config(
                    'tradex_notification_automation.market_sessions.trigger_window_minutes',
                    5
                )
            )
        );

        $sessions = config(
            'tradex_notification_automation.market_sessions.sessions',
            []
        );

        $created = 0;

        foreach (is_array($sessions) ? $sessions : [] as $key => $definition) {
            if (! is_array($definition) || ! ($definition['enabled'] ?? false)) {
                continue;
            }

            $resolved = $this->resolveSession($definition);

            if ($resolved === null) {
                continue;
            }

            $localNow = $nowUtc->setTimezone($resolved['timezone']);

            if (! in_array($localNow->dayOfWeekIso, $resolved['days'], true)) {
                continue;
            }

            $trigger = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $localNow->format('Y-m-d').' '.$resolved['time'],
                $resolved['timezone']
            );

            if (! ($trigger instanceof CarbonImmutable)) {
                continue;
            }

            if (
                $localNow->lessThan($trigger)
                || ! $localNow->lessThan($trigger->addMinutes($windowMinutes))
            ) {
                continue;
            }

            $event = $publisher->publish(
                eventKey: sprintf(
                    'market-session:%s:%s',
                    (string) $key,
                    $localNow->toDateString()
                ),
                eventType: 'MARKET_SESSION_STARTED',
                payload: [
                    'audience' => NotificationMessage::AUDIENCE_ALL,
                    'category' => NotificationMessage::CATEGORY_SYSTEM,
                    'priority' => NotificationMessage::PRIORITY_NORMAL,
                    'title' => $resolved['label'].' Starting',
                    'body' => sprintf(
                        'TradeX session reminder: %s is starting now. Review your plan, risk and session context before trading.',
                        $resolved['label']
                    ),
                    'action_type' => NotificationMessage::ACTION_DASHBOARD,
                    'action_resource_uuid' => null,
                ],
                sourceType: 'MARKET_SESSION',
                sourceReference: (string) $key
            );

            if ($event?->wasRecentlyCreated) {
                $created++;
            }
        }

        $this->components->info(sprintf(
            'Market-session notification scan complete: %d new event(s).',
            $created
        ));

        return self::SUCCESS;
    }

    private function resolveSession(array $definition): ?array
    {
        $label = trim((string) ($definition['label'] ?? 'Market Session'));
        $timezone = trim((string) ($definition['timezone'] ?? ''));
        $time = trim((string) ($definition['time'] ?? ''));
        $days = $definition['days'] ?? [1, 2, 3, 4, 5];
        $marketClockKey = trim((string) ($definition['market_clock_key'] ?? ''));

        if ($marketClockKey !== '') {
            foreach ((array) config('market_clock.sessions', []) as $session) {
                if (
                    is_array($session)
                    && ($session['key'] ?? null) === $marketClockKey
                ) {
                    $timezone = trim((string) ($session['timezone'] ?? ''));
                    $time = trim((string) ($session['open'] ?? ''));
                    $days = $session['days'] ?? $days;
                    break;
                }
            }
        }

        if (
            $timezone === ''
            || (
                $timezone !== 'UTC'
                && ! in_array(
                    $timezone,
                    DateTimeZone::listIdentifiers(),
                    true
                )
            )
            || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)
        ) {
            return null;
        }

        $days = array_values(array_unique(array_filter(
            array_map('intval', is_array($days) ? $days : []),
            static fn (int $day): bool => $day >= 1 && $day <= 7
        )));

        if ($days === []) {
            return null;
        }

        return [
            'label' => $label !== '' ? $label : 'Market Session',
            'timezone' => $timezone,
            'time' => $time,
            'days' => $days,
        ];
    }
}
