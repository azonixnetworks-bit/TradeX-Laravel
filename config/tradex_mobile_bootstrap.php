<?php

return [
    'runtime_state' => 'WIRED_SAFE_BOOTSTRAP',
    'schema_version' => 1,

    /*
     * API-19 intentionally duplicates the exposure allowlist instead of
     * automatically exposing every future ADMIN-14 setting. Adding a new
     * ADMIN setting must never make it public without an explicit API review.
     */
    'safe_general_keys' => [
        'general.platform_name',
        'general.support_email',
        'general.support_whatsapp',
        'general.maintenance_notice_enabled',
        'general.maintenance_notice',
    ],

    'cache_seconds' => 60,

    'rate_limits' => [
        'read_per_minute' => 120,
    ],
];
