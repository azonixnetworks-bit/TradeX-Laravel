<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Market Clock
    |--------------------------------------------------------------------------
    |
    | All calculations are made from UTC and IANA timezones. Never replace an
    | IANA timezone with a fixed UTC offset: London, New York and Sydney are
    | DST-sensitive and their UTC offsets legitimately change during the year.
    |
    | These windows are configurable visual trading-centre sessions for the
    | TradeX global market clock. They are not exchange execution guarantees.
    |
    */

    'default_timezone' => 'UTC',

    'featured_sessions' => [
        [
            'key' => 'asia',
            'name' => 'Asia Session',
            'region' => 'ASIA',
            'timezone' => 'Asia/Tokyo',
            'open' => '09:00',
            'close' => '18:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'london',
            'name' => 'London Session',
            'region' => 'EUROPE',
            'timezone' => 'Europe/London',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'nyse',
            'name' => 'NYSE Session',
            'region' => 'AMERICAS',
            'timezone' => 'America/New_York',
            'open' => '09:30',
            'close' => '16:00',
            'days' => [1, 2, 3, 4, 5],
        ],
    ],

    'sessions' => [
        [
            'key' => 'sydney',
            'name' => 'Sydney',
            'region' => 'ASIA PACIFIC',
            'timezone' => 'Australia/Sydney',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'tokyo',
            'name' => 'Tokyo',
            'region' => 'ASIA PACIFIC',
            'timezone' => 'Asia/Tokyo',
            'open' => '09:00',
            'close' => '18:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'hong_kong',
            'name' => 'Hong Kong',
            'region' => 'ASIA PACIFIC',
            'timezone' => 'Asia/Hong_Kong',
            'open' => '09:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'singapore',
            'name' => 'Singapore',
            'region' => 'ASIA PACIFIC',
            'timezone' => 'Asia/Singapore',
            'open' => '09:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'dubai',
            'name' => 'Dubai',
            'region' => 'MIDDLE EAST',
            'timezone' => 'Asia/Dubai',
            'open' => '09:00',
            'close' => '18:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'frankfurt',
            'name' => 'Frankfurt',
            'region' => 'EUROPE',
            'timezone' => 'Europe/Berlin',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'london',
            'name' => 'London',
            'region' => 'EUROPE',
            'timezone' => 'Europe/London',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'new_york',
            'name' => 'New York',
            'region' => 'AMERICAS',
            'timezone' => 'America/New_York',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'toronto',
            'name' => 'Toronto',
            'region' => 'AMERICAS',
            'timezone' => 'America/Toronto',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'chicago',
            'name' => 'Chicago',
            'region' => 'AMERICAS',
            'timezone' => 'America/Chicago',
            'open' => '08:00',
            'close' => '17:00',
            'days' => [1, 2, 3, 4, 5],
        ],
        [
            'key' => 'sao_paulo',
            'name' => 'Sao Paulo',
            'region' => 'AMERICAS',
            'timezone' => 'America/Sao_Paulo',
            'open' => '09:00',
            'close' => '18:00',
            'days' => [1, 2, 3, 4, 5],
        ],
    ],
];
