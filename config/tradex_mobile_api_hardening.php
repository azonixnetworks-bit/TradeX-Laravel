<?php

return [
    'release_contract' => 'API-23-FINAL',

    'transport' => [
        'allowed_hosts' => array_values(
            array_filter(
                array_map(
                    static fn (string $host): string => strtolower(trim($host)),
                    explode(',', (string) env('TRADEX_API_ALLOWED_HOSTS', ''))
                )
            )
        ),

        'require_https' => filter_var(
            env('TRADEX_API_REQUIRE_HTTPS', false),
            FILTER_VALIDATE_BOOL
        ),

        'hsts_enabled' => filter_var(
            env('TRADEX_API_HSTS_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),

        'hsts_max_age_seconds' => (int) env(
            'TRADEX_API_HSTS_MAX_AGE_SECONDS',
            31536000
        ),

        'hsts_include_subdomains' => filter_var(
            env('TRADEX_API_HSTS_INCLUDE_SUBDOMAINS', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

    'headers' => [
        'request_id' => 'X-Request-Id',
        'authenticated_no_store' => true,
    ],
];
