<?php

return [
    'rate_limits' => [
        'read_per_minute' => (int) env('TRADEX_ACCOUNT_SETTINGS_READ_PER_MINUTE', 120),
        'sensitive_per_minute' => (int) env('TRADEX_ACCOUNT_SETTINGS_SENSITIVE_PER_MINUTE', 10),
    ],
];
