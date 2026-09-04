<?php

return [
    'contact_change' => [
        'expires_minutes' =>
            (int) env(
                'TRADEX_PROFILE_CHANGE_EXPIRES_MINUTES',
                10
            ),

        'max_attempts' =>
            (int) env(
                'TRADEX_PROFILE_CHANGE_MAX_ATTEMPTS',
                5
            ),

        'email_driver' =>
            env(
                'TRADEX_PROFILE_EMAIL_DRIVER',
                'mail'
            ),
    ],

    'rate_limits' => [
        'read_per_minute' => 60,
        'write_per_minute' => 20,
        'sensitive_per_minute' => 10,
        'contact_per_minute' => 8,
    ],

    'testing' => [
        'fixed_email_otp' =>
            env(
                'TRADEX_PROFILE_TEST_FIXED_EMAIL_OTP'
            ),

        'fixed_otp' =>
            env(
                'TRADEX_PROFILE_TEST_FIXED_OTP'
            ),
    ],
];
