<?php

return [
    'customer_devices' => [
        'mobile_active_limit' => (int) env(
            'TRADEX_CUSTOMER_MOBILE_DEVICE_LIMIT',
            2
        ),

        'desktop_active_limit' => (int) env(
            'TRADEX_CUSTOMER_DESKTOP_DEVICE_LIMIT',
            1
        ),
    ],

    'quick_unlock' => [
        'policy_version' => env(
            'TRADEX_QUICK_UNLOCK_POLICY_VERSION',
            'MOBILE-SEC-01-v1'
        ),

        'pin_length' => 4,

        'max_failed_pin_attempts' => (int) env(
            'TRADEX_QUICK_UNLOCK_MAX_FAILED_PIN_ATTEMPTS',
            5
        ),

        'lockout_seconds' => (int) env(
            'TRADEX_QUICK_UNLOCK_LOCKOUT_SECONDS',
            300
        ),

        'background_lock_seconds' => (int) env(
            'TRADEX_QUICK_UNLOCK_BACKGROUND_LOCK_SECONDS',
            1800
        ),

        'inactivity_lock_seconds' => (int) env(
            'TRADEX_QUICK_UNLOCK_INACTIVITY_LOCK_SECONDS',
            300
        ),

        'biometric_allowed' => (bool) env(
            'TRADEX_QUICK_UNLOCK_BIOMETRIC_ALLOWED',
            true
        ),

        'secure_storage_required' => true,

        /*
         * This is a mobile-client enforcement policy in MOBILE-SEC-01.
         * Server-side platform attestation can be added later without
         * changing the local-PIN / biometric transport rule.
         */
        'compromised_device_action' => env(
            'TRADEX_QUICK_UNLOCK_COMPROMISED_DEVICE_ACTION',
            'BLOCK_QUICK_UNLOCK'
        ),

        'rate_limits' => [
            'read_per_minute' => (int) env(
                'TRADEX_QUICK_UNLOCK_READ_PER_MINUTE',
                120
            ),

            'change_per_minute' => (int) env(
                'TRADEX_QUICK_UNLOCK_CHANGE_PER_MINUTE',
                10
            ),

            'challenge_per_minute' => (int) env(
                'TRADEX_QUICK_UNLOCK_CHALLENGE_PER_MINUTE',
                10
            ),

            'verify_per_minute' => (int) env(
                'TRADEX_QUICK_UNLOCK_VERIFY_PER_MINUTE',
                10
            ),
        ],

        'challenge_ttl_seconds' => (int) env(
            'TRADEX_QUICK_UNLOCK_CHALLENGE_TTL_SECONDS',
            120
        ),

        'challenge_max_attempts' => (int) env(
            'TRADEX_QUICK_UNLOCK_CHALLENGE_MAX_ATTEMPTS',
            3
        ),
    ],
];
