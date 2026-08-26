<?php

return [
    'product' => [
        'education_premium' => [
            'code' => 'EDUCATION_PREMIUM',
            'name' => 'TradeX Education Premium',

            'paypal' => [
                'currency' => 'USD',
                'amount_minor' =>
                    (int) env(
                        'TRADEX_EDUCATION_PRICE_USD_MINOR',
                        7900
                    ),
            ],

            'stripe' => [
                'currency' => 'USD',
                'amount_minor' =>
                    (int) env(
                        'TRADEX_EDUCATION_PRICE_USD_MINOR',
                        7900
                    ),
            ],

            'nowpayments' => [
                'currency' => 'USD',
                'amount_minor' =>
                    (int) env(
                        'TRADEX_EDUCATION_PRICE_USD_MINOR',
                        7900
                    ),
            ],

            'local_bank' => [
                'currency' => 'LKR',
                'amount_minor' =>
                    (int) env(
                        'TRADEX_EDUCATION_PRICE_LKR_MINOR',
                        0
                    ),
            ],

            'usdt_trc20' => [
                'currency' => 'USDT',
                'amount_minor' =>
                    (int) env(
                        'TRADEX_EDUCATION_PRICE_USDT_MICRO',
                        79000000
                    ),
            ],
        ],
    ],

    'paypal' => [
        'driver' =>
            env(
                'TRADEX_PAYPAL_DRIVER',
                'http'
            ),

        'enabled' =>
            filter_var(
                env(
                    'TRADEX_PAYPAL_ENABLED',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),

        'countries' =>
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            (string) env(
                                'TRADEX_PAYPAL_COUNTRIES',
                                'LK,IN'
                            )
                        )
                    )
                )
            ),

        'environment' =>
            env(
                'TRADEX_PAYPAL_ENVIRONMENT',
                'sandbox'
            ),

        'client_id' =>
            env(
                'TRADEX_PAYPAL_CLIENT_ID'
            ),

        'client_secret' =>
            env(
                'TRADEX_PAYPAL_CLIENT_SECRET'
            ),

        'webhook_id' =>
            env(
                'TRADEX_PAYPAL_WEBHOOK_ID'
            ),

        'sandbox_base_url' =>
            env(
                'TRADEX_PAYPAL_SANDBOX_BASE_URL',
                'https://api-m.sandbox.paypal.com'
            ),

        'live_base_url' =>
            env(
                'TRADEX_PAYPAL_LIVE_BASE_URL',
                'https://api-m.paypal.com'
            ),

        'return_url' =>
            env(
                'TRADEX_PAYPAL_RETURN_URL'
            ),

        'cancel_url' =>
            env(
                'TRADEX_PAYPAL_CANCEL_URL'
            ),

        'timeout_seconds' =>
            (int) env(
                'TRADEX_PAYPAL_TIMEOUT_SECONDS',
                15
            ),
    ],

    'local_bank' => [
        'enabled' =>
            filter_var(
                env(
                    'TRADEX_LOCAL_BANK_ENABLED',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),

        'countries' =>
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            (string) env(
                                'TRADEX_LOCAL_BANK_COUNTRIES',
                                'LK'
                            )
                        )
                    )
                )
            ),

        'bank_name' =>
            env(
                'TRADEX_LOCAL_BANK_NAME'
            ),

        'branch' =>
            env(
                'TRADEX_LOCAL_BANK_BRANCH'
            ),

        'account_holder' =>
            env(
                'TRADEX_LOCAL_BANK_ACCOUNT_HOLDER'
            ),

        'account_number' =>
            env(
                'TRADEX_LOCAL_BANK_ACCOUNT_NUMBER'
            ),

        'proof_disk' =>
            env(
                'TRADEX_PAYMENT_PROOF_DISK',
                'local'
            ),

        'proof_max_kb' =>
            (int) env(
                'TRADEX_PAYMENT_PROOF_MAX_KB',
                5120
            ),
    ],

    'usdt_trc20' => [
        'enabled' =>
            filter_var(
                env(
                    'TRADEX_USDT_TRC20_ENABLED',
                    true
                ),
                FILTER_VALIDATE_BOOL
            ),

        'countries' =>
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            (string) env(
                                'TRADEX_USDT_TRC20_COUNTRIES',
                                'IN'
                            )
                        )
                    )
                )
            ),

        'wallet_address' =>
            env(
                'TRADEX_USDT_TRC20_WALLET'
            ),

        'network' =>
            'TRC20',

        'token' =>
            'USDT',
    ],

    'pagination' => [
        'default_per_page' =>
            (int) env(
                'TRADEX_PAYMENTS_PER_PAGE',
                20
            ),

        'max_per_page' =>
            (int) env(
                'TRADEX_PAYMENTS_MAX_PER_PAGE',
                50
            ),
    ],

    'rate_limits' => [
        'read_per_minute' =>
            (int) env(
                'TRADEX_PAYMENTS_READ_PER_MINUTE',
                120
            ),

        'create_per_minute' =>
            (int) env(
                'TRADEX_PAYMENTS_CREATE_PER_MINUTE',
                20
            ),

        'capture_per_minute' =>
            (int) env(
                'TRADEX_PAYMENTS_CAPTURE_PER_MINUTE',
                10
            ),

        'webhook_per_minute' =>
            (int) env(
                'TRADEX_PAYMENTS_WEBHOOK_PER_MINUTE',
                120
            ),
    ],
];
