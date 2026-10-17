<?php

return [
    'access_token_minutes' => 15,
    'refresh_token_days' => 30,

    'rate_limits' => [
        'login_per_minute' => 5,
        'login_per_hour_ip' => 30,
        'login_failed_attempts' => 5,
        'login_lockout_seconds' => 900,
        'refresh_per_minute' => 30,
        'share_card_per_minute' => 30,
    ],

    'totp' => [
        'period_seconds' => 30,
        'digits' => 6,
        'window' => 1,
    ],

    'recovery_codes' => [
        'count' => 10,
        'pepper' => env(
            'TRADEX_MFA_RECOVERY_PEPPER',
            env('APP_KEY')
        ),
    ],
];
