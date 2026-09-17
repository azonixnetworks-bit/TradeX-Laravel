<?php

return [
    'reminders' => [
        'automation_enabled' => (bool) env('TRADING_FLOOR_REMINDER_AUTOMATION_ENABLED', false),
        'schedule_time' => env('TRADING_FLOOR_REMINDER_SCHEDULE_TIME', '08:00'),
        'timezone' => env('TRADING_FLOOR_REMINDER_TIMEZONE', 'Asia/Colombo'),
    ],
];
