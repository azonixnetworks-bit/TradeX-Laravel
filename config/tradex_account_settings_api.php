<?php

return [
    'rate_limits' => [
        'read_per_minute' => (int) env('TRADEX_ACCOUNT_SETTINGS_READ_PER_MINUTE', 120),
        'sensitive_per_minute' => (int) env('TRADEX_ACCOUNT_SETTINGS_SENSITIVE_PER_MINUTE', 10),
        'photo_uploads_per_minute' => (int) env('TRADEX_PROFILE_PHOTO_UPLOADS_PER_MINUTE', 2),
        'photo_uploads_per_hour' => (int) env('TRADEX_PROFILE_PHOTO_UPLOADS_PER_HOUR', 5),
    ],
];
