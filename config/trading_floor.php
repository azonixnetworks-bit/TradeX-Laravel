<?php

return [
    'reminders' => [
        'automation_enabled' => (bool) env('TRADING_FLOOR_REMINDER_AUTOMATION_ENABLED', false),
        'schedule_time' => env('TRADING_FLOOR_REMINDER_SCHEDULE_TIME', '08:00'),
        'timezone' => env('TRADING_FLOOR_REMINDER_TIMEZONE', 'Asia/Colombo'),
    ],

    'memberships' => [
        'sweep_enabled' => (bool) env('TRADING_FLOOR_MEMBERSHIP_SWEEP_ENABLED', true),
        'schedule_time' => env('TRADING_FLOOR_MEMBERSHIP_SWEEP_TIME', '00:15'),
        'timezone' => env('TRADING_FLOOR_MEMBERSHIP_TIMEZONE', 'Asia/Colombo'),
    ],
];
