<?php

$countryList = static function (
    string $envKey,
    string $default = ''
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
                    preg_match(
                        '/^[A-Z]{2}$/',
                        $value
                    ) === 1
            )
        )
    );
};

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
            'transaction_owner' => 'Future Payments API provider extension',
            'runtime_adapter_status' => 'NOT_WIRED_ADMIN16',
            'environment_options' => ['SANDBOX', 'LIVE'],
        ],

        'NOWPAYMENTS' => [
            'display_name' => 'NOWPayments',
            'kind' => 'CRYPTO_PAYMENT_GATEWAY',
            'transaction_owner' => 'Future Payments API provider extension',
            'runtime_adapter_status' => 'NOT_WIRED_ADMIN16',
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
            filter_var(
                env(
                    'TRADEX_STRIPE_ENABLED',
                    false
                ),
                FILTER_VALIDATE_BOOL
            ),

        'countries' =>
            $countryList(
                'TRADEX_STRIPE_COUNTRIES'
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
    ],

    'nowpayments' => [
        'enabled' =>
            filter_var(
                env(
                    'TRADEX_NOWPAYMENTS_ENABLED',
                    false
                ),
                FILTER_VALIDATE_BOOL
            ),

        'countries' =>
            $countryList(
                'TRADEX_NOWPAYMENTS_COUNTRIES'
            ),

        'environment' =>
            strtoupper(
                (string) env(
                    'TRADEX_NOWPAYMENTS_ENVIRONMENT',
                    'SANDBOX'
                )
            ),

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
                'TRADEX_NOWPAYMENTS_SANDBOX_BASE_URL'
            ),

        'live_base_url' =>
            env(
                'TRADEX_NOWPAYMENTS_LIVE_BASE_URL'
            ),
    ],
];
