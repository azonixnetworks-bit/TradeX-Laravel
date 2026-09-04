<?php

return [
    'session_minutes' =>
        (int) env(
            'TRADEX_REG_SESSION_MINUTES',
            60
        ),

    'otp' => [
        'digits' =>
            (int) env(
                'TRADEX_REG_OTP_DIGITS',
                6
            ),

        'expires_minutes' =>
            (int) env(
                'TRADEX_REG_OTP_EXPIRES_MINUTES',
                10
            ),

        'resend_cooldown_seconds' =>
            (int) env(
                'TRADEX_REG_OTP_RESEND_COOLDOWN_SECONDS',
                60
            ),

        'max_attempts' =>
            (int) env(
                'TRADEX_REG_OTP_MAX_ATTEMPTS',
                5
            ),
    ],

    'allowed_languages' => [
        'en',
        'ta',
        'si',
        'hi',
        'ml',
        'te',
        'ur',
    ],

    'totp_issuer' =>
        env(
            'TRADEX_TOTP_ISSUER',
            'TradeX OS'
        ),

    'delivery' => [
        'email_driver' =>
            env(
                'TRADEX_REG_EMAIL_DRIVER',
                'mail'
            ),

        'whatsapp_driver' =>
            env(
                'TRADEX_REG_WHATSAPP_DRIVER',
                'log'
            ),

        'meta' => [
            'graph_version' =>
                env(
                    'META_WHATSAPP_GRAPH_VERSION'
                ),

            'phone_number_id' =>
                env(
                    'META_WHATSAPP_PHONE_NUMBER_ID'
                ),

            'access_token' =>
                env(
                    'META_WHATSAPP_ACCESS_TOKEN'
                ),

            'otp_template_name' =>
                env(
                    'META_WHATSAPP_OTP_TEMPLATE_NAME'
                ),

            'otp_template_language' =>
                env(
                    'META_WHATSAPP_OTP_TEMPLATE_LANGUAGE',
                    'en_US'
                ),
        ],
    ],

    'rate_limits' => [
        'start_per_minute' => 3,
        'start_per_hour_ip' => 20,
        'otp_send_per_minute' => 3,
        'verify_per_minute' => 10,
        'totp_per_minute' => 10,
    ],

    'testing' => [
        'fixed_email_otp' =>
            env(
                'TRADEX_REG_TEST_FIXED_EMAIL_OTP'
            ),

        'fixed_otp' =>
            env(
                'TRADEX_REG_TEST_FIXED_OTP'
            ),
    ],
];
