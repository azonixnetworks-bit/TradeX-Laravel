<?php

return [
    'access_token_minutes' => 15,
    'refresh_token_days' => 30,

    /*
     * A customer may keep at most this many active trusted devices.
     * Device category (mobile/desktop) is descriptive only; the limit applies
     * across all supported platforms. New devices still require strong login
     * authentication before they can be trusted.
     */
    'max_trusted_devices' => max(
        1,
        (int) env('TRADEX_MAX_TRUSTED_DEVICES', 2)
    ),

    'rate_limits' => [
        'login_per_minute' => 5,
        'login_per_hour_ip' => 30,
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
