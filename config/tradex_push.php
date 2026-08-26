<?php

return [
    'enabled' => (bool) env('TRADEX_PUSH_ENABLED', false),

    'queue' => env('TRADEX_PUSH_QUEUE', 'push-notifications'),

    'privacy_safe_title' => env(
        'TRADEX_PUSH_SAFE_TITLE',
        'TradeX OS'
    ),

    'privacy_safe_body' => env(
        'TRADEX_PUSH_SAFE_BODY',
        'You have a new notification.'
    ),

    'max_attempts' => 5,

    'retry_backoff_seconds' => [
        60,
        300,
        900,
        3600,
    ],

    'sweep' => [
        'batch_size' => 500,
        'schedule_every_minute' => true,
    ],

    'rate_limits' => [
        'device_reads_per_minute' => 60,
        'device_mutations_per_minute' => 20,
    ],

    'fcm' => [
        'enabled' => (bool) env('TRADEX_PUSH_FCM_ENABLED', false),
        'project_id' => env('TRADEX_PUSH_FCM_PROJECT_ID'),
        'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),
        'token_uri' => 'https://oauth2.googleapis.com/token',
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'send_endpoint' =>
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
        'timeout_seconds' => 10,
    ],

    'apns' => [
        'enabled' => (bool) env('TRADEX_PUSH_APNS_ENABLED', false),
        'environment' => env(
            'TRADEX_PUSH_APNS_ENVIRONMENT',
            'production'
        ),
        'team_id' => env('TRADEX_PUSH_APNS_TEAM_ID'),
        'key_id' => env('TRADEX_PUSH_APNS_KEY_ID'),
        'bundle_id' => env('TRADEX_PUSH_APNS_BUNDLE_ID'),
        'private_key_path' => env('TRADEX_PUSH_APNS_PRIVATE_KEY_PATH'),
        'production_host' => 'https://api.push.apple.com',
        'sandbox_host' => 'https://api.sandbox.push.apple.com',
        'timeout_seconds' => 10,
    ],
];
