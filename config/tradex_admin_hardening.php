<?php

$allowedHosts = array_values(
    array_unique(
        array_filter(
            array_map(
                static fn (string $host): string =>
                    strtolower(trim($host)),
                explode(
                    ',',
                    (string) env(
                        'TRADEX_ADMIN_ALLOWED_HOSTS',
                        ''
                    )
                )
            )
        )
    )
);

return [
    /*
    |--------------------------------------------------------------------------
    | ADMIN-19 — Admin Website Final Hardening
    |--------------------------------------------------------------------------
    |
    | Local/test-safe defaults keep the existing development workflow usable.
    | Production release readiness requires the deployment flags documented in
    | ADMIN-19 to be explicitly enabled after every active admin has enrolled
    | TOTP.
    |
    */

    'session' => [
        'idle_timeout_minutes' =>
            (int) env(
                'TRADEX_ADMIN_IDLE_TIMEOUT_MINUTES',
                30
            ),

        'absolute_timeout_minutes' =>
            (int) env(
                'TRADEX_ADMIN_ABSOLUTE_TIMEOUT_MINUTES',
                480
            ),
    ],

    'totp' => [
        /*
         * Rollout sequence:
         * 1. keep false,
         * 2. every active admin enrolls TOTP,
         * 3. production release check,
         * 4. set true in production.
         */
        'required' =>
            filter_var(
                env(
                    'TRADEX_ADMIN_TOTP_REQUIRED',
                    false
                ),
                FILTER_VALIDATE_BOOL
            ),

        'issuer' =>
            (string) env(
                'TRADEX_ADMIN_TOTP_ISSUER',
                'TradeX OS Admin'
            ),

        'setup_window_minutes' =>
            (int) env(
                'TRADEX_ADMIN_TOTP_SETUP_WINDOW_MINUTES',
                15
            ),

        'login_challenge_minutes' =>
            (int) env(
                'TRADEX_ADMIN_TOTP_CHALLENGE_MINUTES',
                5
            ),

        'challenge_attempts_per_minute' =>
            (int) env(
                'TRADEX_ADMIN_TOTP_ATTEMPTS_PER_MINUTE',
                5
            ),
    ],

    'transport' => [
        'allowed_hosts' =>
            $allowedHosts,

        'require_https' =>
            filter_var(
                env(
                    'TRADEX_ADMIN_REQUIRE_HTTPS',
                    false
                ),
                FILTER_VALIDATE_BOOL
            ),

        'hsts_enabled' =>
            filter_var(
                env(
                    'TRADEX_ADMIN_HSTS_ENABLED',
                    false
                ),
                FILTER_VALIDATE_BOOL
            ),

        'hsts_max_age_seconds' =>
            (int) env(
                'TRADEX_ADMIN_HSTS_MAX_AGE_SECONDS',
                31536000
            ),

        'hsts_include_subdomains' =>
            filter_var(
                env(
                    'TRADEX_ADMIN_HSTS_INCLUDE_SUBDOMAINS',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),
    ],

    'headers' => [
        'csp_enabled' =>
            filter_var(
                env(
                    'TRADEX_ADMIN_CSP_ENABLED',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),

        /*
         * Existing admin pages use inline CSS/style attributes.
         * Scripts are loaded from same-origin static assets.
         */
        'content_security_policy' =>
            "default-src 'self'; "
            ."base-uri 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'; "
            ."object-src 'none'; "
            ."img-src 'self' data: https://i.ytimg.com; "
            ."font-src 'self' data:; "
            ."style-src 'self' 'unsafe-inline'; "
            ."script-src 'self' 'unsafe-inline'; "
            ."connect-src 'self';",
    ],
];
