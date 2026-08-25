<?php

return [
    'credential_max_length' => 20000,
    'challenge_minutes' => 5,
    'clock_skew_seconds' => 60,
    'jwks_cache_seconds' => 3600,
    'http_timeout_seconds' => 5,

    'providers' => [
        'google' => [
            'enabled' => (bool) env('TRADEX_SOCIAL_GOOGLE_ENABLED', false),
            'client_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('TRADEX_SOCIAL_GOOGLE_CLIENT_IDS', ''))
            ))),
            'issuer' => [
                'https://accounts.google.com',
                'accounts.google.com',
            ],
            'jwks_url' => 'https://www.googleapis.com/oauth2/v3/certs',
            'require_nonce' => (bool) env('TRADEX_SOCIAL_GOOGLE_REQUIRE_NONCE', false),
        ],

        'facebook' => [
            'enabled' => (bool) env('TRADEX_SOCIAL_FACEBOOK_ENABLED', false),
            'app_id' => env('TRADEX_SOCIAL_FACEBOOK_APP_ID'),
            'app_secret' => env('TRADEX_SOCIAL_FACEBOOK_APP_SECRET'),
            'graph_base_url' => rtrim(
                (string) env('TRADEX_SOCIAL_FACEBOOK_GRAPH_BASE_URL', 'https://graph.facebook.com'),
                '/'
            ),
            'require_nonce' => false,
        ],

        'apple' => [
            'enabled' => (bool) env('TRADEX_SOCIAL_APPLE_ENABLED', false),
            'client_ids' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('TRADEX_SOCIAL_APPLE_CLIENT_IDS', ''))
            ))),
            'issuer' => ['https://appleid.apple.com'],
            'jwks_url' => 'https://appleid.apple.com/auth/keys',
            'require_nonce' => true,
        ],
    ],

    'rate_limits' => [
        'challenge_per_minute' => 10,
        'login_per_minute' => 5,
        'onboard_per_minute' => 3,
        'confirm_per_minute' => 5,
        'link_per_minute' => 5,
    ],
];
