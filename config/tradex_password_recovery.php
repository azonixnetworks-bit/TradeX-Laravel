<?php

return [
    'session_minutes' => (int) env('TRADEX_PASSWORD_RECOVERY_SESSION_MINUTES', 30),
    'reset_token_minutes' => (int) env('TRADEX_PASSWORD_RECOVERY_RESET_TOKEN_MINUTES', 15),

    'hash_key' => env(
        'TRADEX_PASSWORD_RECOVERY_HASH_KEY',
        env('APP_KEY')
    ),

    'reset_link_url' => env(
        'TRADEX_PASSWORD_RESET_URL',
        'tradex://password-reset'
    ),

    'otp' => [
        'digits' => (int) env('TRADEX_PASSWORD_RECOVERY_OTP_DIGITS', 6),
        'expires_minutes' => (int) env('TRADEX_PASSWORD_RECOVERY_OTP_EXPIRES_MINUTES', 10),
        'resend_cooldown_seconds' => (int) env(
            'TRADEX_PASSWORD_RECOVERY_OTP_RESEND_COOLDOWN_SECONDS',
            60
        ),
        'max_attempts' => (int) env('TRADEX_PASSWORD_RECOVERY_OTP_MAX_ATTEMPTS', 5),
    ],

    'delivery' => [
        'email_driver' => env(
            'TRADEX_PASSWORD_RECOVERY_EMAIL_DRIVER',
            env('TRADEX_REG_EMAIL_DRIVER', 'mail')
        ),

        'whatsapp_driver' => env(
            'TRADEX_PASSWORD_RECOVERY_WHATSAPP_DRIVER',
            env('TRADEX_REG_WHATSAPP_DRIVER', 'log')
        ),

        'meta' => [
            'graph_version' => env('META_WHATSAPP_GRAPH_VERSION'),
            'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),
            'otp_template_name' => env(
                'META_WHATSAPP_PASSWORD_RECOVERY_OTP_TEMPLATE_NAME',
                env('META_WHATSAPP_OTP_TEMPLATE_NAME')
            ),
            'otp_template_language' => env(
                'META_WHATSAPP_PASSWORD_RECOVERY_OTP_TEMPLATE_LANGUAGE',
                env('META_WHATSAPP_OTP_TEMPLATE_LANGUAGE', 'en_US')
            ),
        ],
    ],

    'rate_limits' => [
        'start_per_minute' => (int) env('TRADEX_PASSWORD_RECOVERY_START_PER_MINUTE', 3),
        'start_per_hour_ip' => (int) env('TRADEX_PASSWORD_RECOVERY_START_PER_HOUR_IP', 20),
        'verify_per_minute' => (int) env('TRADEX_PASSWORD_RECOVERY_VERIFY_PER_MINUTE', 10),
        'resend_per_minute' => (int) env('TRADEX_PASSWORD_RECOVERY_RESEND_PER_MINUTE', 3),
        'reset_per_minute' => (int) env('TRADEX_PASSWORD_RECOVERY_RESET_PER_MINUTE', 5),
    ],

    'testing' => [
        'fixed_email_otp' => env(
            'TRADEX_PASSWORD_RECOVERY_TEST_FIXED_EMAIL_OTP'
        ),
        'fixed_otp' => env('TRADEX_PASSWORD_RECOVERY_TEST_FIXED_OTP'),
        'fixed_reset_token' => env('TRADEX_PASSWORD_RECOVERY_TEST_FIXED_RESET_TOKEN'),
    ],
];
