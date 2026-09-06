<?php

return [
    /*
    |--------------------------------------------------------------------------
    | TradeX Notification Event Automation
    |--------------------------------------------------------------------------
    |
    | The notification centre and push-delivery layers remain authoritative.
    | This module only converts approved business events into notification
    | messages + recipient receipts. It is disabled by default until the
    | deployment has migrations, scheduler, queue workers and push credentials.
    |
    */
    'enabled' => env('TRADEX_NOTIFICATION_AUTOMATION_ENABLED', false),

    'immediate_processing' => env(
        'TRADEX_NOTIFICATION_AUTOMATION_IMMEDIATE',
        true
    ),

    'immediate_push_fanout' => env(
        'TRADEX_NOTIFICATION_AUTOMATION_PUSH_IMMEDIATE',
        true
    ),

    'sweep' => [
        'batch_size' => (int) env(
            'TRADEX_NOTIFICATION_AUTOMATION_SWEEP_BATCH',
            100
        ),
        'max_attempts' => (int) env(
            'TRADEX_NOTIFICATION_AUTOMATION_MAX_ATTEMPTS',
            5
        ),
        'recipient_chunk_size' => (int) env(
            'TRADEX_NOTIFICATION_AUTOMATION_RECIPIENT_CHUNK',
            1000
        ),
        'max_recipients' => (int) env(
            'TRADEX_NOTIFICATION_AUTOMATION_MAX_RECIPIENTS',
            250000
        ),
        'retry_seconds' => [60, 300, 900, 3600, 21600],
    ],

    'events' => [
        'trade_ideas' => env(
            'TRADEX_NOTIFICATION_EVENT_TRADE_IDEAS',
            true
        ),
        'referrals' => env(
            'TRADEX_NOTIFICATION_EVENT_REFERRALS',
            true
        ),
        'market_sessions' => env(
            'TRADEX_NOTIFICATION_EVENT_MARKET_SESSIONS',
            true
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Market-session reminders
    |--------------------------------------------------------------------------
    |
    | Asia, London and New York AM inherit the already-frozen Market Clock IANA
    | timezone + opening time, so DST follows the canonical market-clock config.
    | New York PM is intentionally NOT guessed. Enable it only after the exact
    | TradeX internal PM-session time is approved and configured below.
    |
    | These are TradeX planning reminders, not exchange execution guarantees.
    |
    */
    'market_sessions' => [
        'trigger_window_minutes' => (int) env(
            'TRADEX_NOTIFICATION_MARKET_TRIGGER_WINDOW_MINUTES',
            5
        ),
        'sessions' => [
            'asia' => [
                'enabled' => env(
                    'TRADEX_NOTIFICATION_ASIA_SESSION_ENABLED',
                    true
                ),
                'label' => 'Asia Session',
                'market_clock_key' => 'tokyo',
            ],
            'london' => [
                'enabled' => env(
                    'TRADEX_NOTIFICATION_LONDON_SESSION_ENABLED',
                    true
                ),
                'label' => 'London Session',
                'market_clock_key' => 'london',
            ],
            'new_york_am' => [
                'enabled' => env(
                    'TRADEX_NOTIFICATION_NEW_YORK_AM_ENABLED',
                    true
                ),
                'label' => 'New York AM Session',
                'market_clock_key' => 'new_york',
            ],
            'new_york_pm' => [
                'enabled' => env(
                    'TRADEX_NOTIFICATION_NEW_YORK_PM_ENABLED',
                    false
                ),
                'label' => 'New York PM Session',
                'timezone' => 'America/New_York',
                'time' => env('TRADEX_NOTIFICATION_NEW_YORK_PM_TIME'),
                'days' => [1, 2, 3, 4, 5],
            ],
        ],
    ],
];
