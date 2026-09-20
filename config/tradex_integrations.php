<?php

$countryList = static function (
    string $envKey,
    string $default = '',
    bool $allowWildcard = false
): array {
    return array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn (string $value): string =>
                        strtoupper(trim($value)),
                    explode(
                        ',',
                        (string) env(
                            $envKey,
                            $default
                        )
                    )
                ),
                static fn (string $value): bool =>
                    (
                        $allowWildcard
                        && $value === '*'
                    )
                    || preg_match(
                        '/^[A-Z]{2}$/',
                        $value
                    ) === 1
            )
        )
    );
};

$stripeEnabled =
    filter_var(
        env(
            'TRADEX_STRIPE_ENABLED',
            false
        ),
        FILTER_VALIDATE_BOOL
    );

$stripeAdapterReady =
    $stripeEnabled
    && filled(
        env(
            'TRADEX_STRIPE_PUBLISHABLE_KEY'
        )
    )
    && filled(
        env(
            'TRADEX_STRIPE_SECRET_KEY'
        )
    )
    && filled(
        env(
            'TRADEX_STRIPE_WEBHOOK_SECRET'
        )
    )
    && filled(
        env(
            'TRADEX_STRIPE_SUCCESS_URL'
        )
    )
    && filled(
        env(
            'TRADEX_STRIPE_CANCEL_URL'
        )
    );

$nowPaymentsEnabled =
    filter_var(
        env(
            'TRADEX_NOWPAYMENTS_ENABLED',
            false
        ),
        FILTER_VALIDATE_BOOL
    );

$nowPaymentsEnvironment =
    strtoupper(
        (string) env(
            'TRADEX_NOWPAYMENTS_ENVIRONMENT',
            'SANDBOX'
        )
    );

return [
    'providers' => [
        'PAYPAL' => [
            'display_name' => 'PayPal',
            'kind' => 'PAYMENT_GATEWAY',
            'transaction_owner' => 'Existing Payments API / ADMIN-07',
            'runtime_adapter_status' => 'ACTIVE_EXISTING',
            'environment_options' => ['SANDBOX', 'LIVE'],
        ],

        'STRIPE' => [
            'display_name' => 'Stripe',
            'kind' => 'PAYMENT_GATEWAY',
            'transaction_owner' => 'Payments API / API-21',
            'runtime_adapter_status' =>
                $stripeAdapterReady
                    ? 'ACTIVE_EXISTING'
                    : 'NOT_WIRED_ADMIN16',
            'environment_options' => ['SANDBOX', 'LIVE'],
        ],

        'NOWPAYMENTS' => [
            'display_name' => 'NOWPayments',
            'kind' => 'CRYPTO_PAYMENT_GATEWAY',
            'transaction_owner' => 'Payments API / API-21',
            'runtime_adapter_status' => 'ACTIVE_EXISTING',
            'environment_options' => ['SANDBOX', 'LIVE'],
        ],

        'LOCAL_BANK' => [
            'display_name' => 'Local Bank Deposit',
            'kind' => 'MANUAL_PAYMENT',
            'transaction_owner' => 'Existing Payments API / ADMIN-07',
            'runtime_adapter_status' => 'ACTIVE_EXISTING',
            'environment_options' => ['MANUAL'],
        ],
    ],

    'stripe' => [
        'enabled' =>
            $stripeEnabled,

        'countries' =>
            $countryList(
                'TRADEX_STRIPE_COUNTRIES',
                '',
                true
            ),

        'environment' =>
            strtoupper(
                (string) env(
                    'TRADEX_STRIPE_ENVIRONMENT',
                    'SANDBOX'
                )
            ),

        'publishable_key' =>
            env(
                'TRADEX_STRIPE_PUBLISHABLE_KEY'
            ),

        'secret_key' =>
            env(
                'TRADEX_STRIPE_SECRET_KEY'
            ),

        'webhook_secret' =>
            env(
                'TRADEX_STRIPE_WEBHOOK_SECRET'
            ),

        'base_url' =>
            env(
                'TRADEX_STRIPE_BASE_URL',
                'https://api.stripe.com'
            ),

        'success_url' =>
            env(
                'TRADEX_STRIPE_SUCCESS_URL'
            ),

        'cancel_url' =>
            env(
                'TRADEX_STRIPE_CANCEL_URL'
            ),

        'timeout_seconds' =>
            (int) env(
                'TRADEX_STRIPE_TIMEOUT_SECONDS',
                15
            ),

        'webhook_tolerance_seconds' =>
            (int) env(
                'TRADEX_STRIPE_WEBHOOK_TOLERANCE_SECONDS',
                300
            ),
    ],

    'nowpayments' => [
        'enabled' =>
            $nowPaymentsEnabled,

        'countries' =>
            $countryList(
                'TRADEX_NOWPAYMENTS_COUNTRIES'
            ),

        'environment' =>
            $nowPaymentsEnvironment,

        'api_key' =>
            env(
                'TRADEX_NOWPAYMENTS_API_KEY'
            ),

        'ipn_secret' =>
            env(
                'TRADEX_NOWPAYMENTS_IPN_SECRET'
            ),

        'sandbox_base_url' =>
            env(
                'TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL',
                'https://api.sandbox.nowpayments.io'
            ),

        'live_base_url' =>
            env(
                'TRADEX_NOWPAYMENTS_LIVE_BASE_URL',
                'https://api.nowpayments.io'
            ),

        'success_url' =>
            env(
                'TRADEX_NOWPAYMENTS_SUCCESS_URL'
            ),

        'cancel_url' =>
            env(
                'TRADEX_NOWPAYMENTS_CANCEL_URL'
            ),

        'timeout_seconds' =>
            (int) env(
                'TRADEX_NOWPAYMENTS_TIMEOUT_SECONDS',
                15
            ),
    ],
];
