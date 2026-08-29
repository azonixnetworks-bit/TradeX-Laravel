<?php

return [
    'market_categories' => [
        'FOREX',
        'DERIV',
        'CRYPTO',
    ],

    'required_currency' => env(
        'TRADEX_COPY_TRADE_REQUIRED_CURRENCY',
        'USD'
    ),

    'max_account_number_digits' => 15,

    'max_accounts_per_market' => 5,

    'max_resubmissions' => 3,

    'onboarding_intent_ttl_days' => 30,

    'review_evidence_max_kb' => 10240,

    /*
    |--------------------------------------------------------------------------
    | ADMIN-23 — Copy Trading Activation Foundation
    |--------------------------------------------------------------------------
    |
    | Frozen Module 12 rules:
    | - Verified Billable Balance uses settled eligible account balance only.
    | - Floating equity/open-position P/L is not the billing balance.
    | - Accounts below USD 250 are inactive / ineligible.
    | - First 1,000 eligible education users receive Account 1 lifetime-free
    |   separately in Forex, Deriv and Crypto.
    | - User 1,001 onward receives 30 consecutive calendar days free for
    |   Account 1 in each category from effective activation.
    | - Accounts 2-5 are paid.
    | - Benefits are non-transferable and non-resettable.
    |
    */

    'activation' => [
        'minimum_verified_billable_balance_minor' => 25000,

        'currency' => 'USD',

        'first_eligible_users_lifetime_free_count' => 1000,

        'later_user_account_1_free_days' => 30,

        'balance_sources' => [
            'ADMIN_MANUAL',
            'BROKER_API',
            'SYSTEM_IMPORT',
        ],

        'trigger_sources' => [
            'ADMIN',
            'SYSTEM',
            'BROKER_API',
        ],

        'gate_codes' => [
            'BROKER_PARTNER_ELIGIBILITY',
            'VERIFIED_BILLABLE_BALANCE',
            'ACCOUNT_BENEFIT_ASSIGNMENT',
            'ENTITLEMENT',
        ],

        'benefit_types' => [
            'LIFETIME_FREE_ACCOUNT_1',
            'THIRTY_DAY_FREE_ACCOUNT_1',
            'PAID_REQUIRED',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ADMIN-24 — Copy Trading Billing & Renewal
    |--------------------------------------------------------------------------
    |
    | Module 12 freezes the commercial formula, but it does not freeze:
    | - a numeric grace-period duration,
    | - an exact payment gateway implementation,
    | - tax rates / jurisdictional invoice wording,
    | - the IC Markets server timezone value.
    |
    | These values are therefore never guessed by code.
    |
    */

    'billing' => [
        'currency' => 'USD',

        'pricing_tiers' => [
            [
                'code' => 'USD_250_999',
                'min_balance_minor' => 25000,
                'max_balance_minor' => 100000,
                'full_monthly_fee_minor' => 500,
            ],
            [
                'code' => 'USD_1000_2999',
                'min_balance_minor' => 100000,
                'max_balance_minor' => 300000,
                'full_monthly_fee_minor' => 1000,
            ],
            [
                'code' => 'USD_3000_9999',
                'min_balance_minor' => 300000,
                'max_balance_minor' => 1000000,
                'full_monthly_fee_minor' => 2000,
            ],
            [
                'code' => 'USD_10000_49999',
                'min_balance_minor' => 1000000,
                'max_balance_minor' => 5000000,
                'full_monthly_fee_minor' => 5000,
            ],
            [
                'code' => 'USD_50000_99999',
                'min_balance_minor' => 5000000,
                'max_balance_minor' => 10000000,
                'full_monthly_fee_minor' => 10000,
            ],
            [
                'code' => 'USD_100000_199999',
                'min_balance_minor' => 10000000,
                'max_balance_minor' => 20000000,
                'full_monthly_fee_minor' => 19900,
            ],
            [
                'code' => 'USD_200000_299999',
                'min_balance_minor' => 20000000,
                'max_balance_minor' => 30000000,
                'full_monthly_fee_minor' => 29900,
            ],
            [
                'code' => 'USD_300000_499999',
                'min_balance_minor' => 30000000,
                'max_balance_minor' => 50000000,
                'full_monthly_fee_minor' => 39900,
            ],
            [
                'code' => 'USD_500000_999999',
                'min_balance_minor' => 50000000,
                'max_balance_minor' => 100000000,
                'full_monthly_fee_minor' => 59900,
            ],
            [
                'code' => 'USD_1000000_PLUS',
                'min_balance_minor' => 100000000,
                'max_balance_minor' => null,
                'full_monthly_fee_minor' => 99900,
            ],
        ],

        /*
         * Required before Forex billing can run.
         * Use the approved IC Markets server timezone.
         */
        'forex_server_timezone' =>
            env('TRADEX_COPY_TRADE_FOREX_SERVER_TIMEZONE'),

        'calendar_sources' => [
            'ADMIN_MANUAL',
            'BROKER_API',
            'SYSTEM_IMPORT',
        ],

        /*
         * Frozen Module 12 says "applicable grace period" but does not define
         * its numeric duration. Production must set the approved value.
         */
        'grace_period_days' =>
            is_numeric(
                env('TRADEX_COPY_TRADE_GRACE_PERIOD_DAYS')
            )
                ? (int) env(
                    'TRADEX_COPY_TRADE_GRACE_PERIOD_DAYS'
                )
                : null,

        /*
         * Disabled by default until operational calendar/grace settings
         * and the approved payment adapter are deployed.
         */
        'sweep_enabled' =>
            (bool) env(
                'TRADEX_COPY_TRADE_BILLING_SWEEP_ENABLED',
                false
            ),
    ],

    /*
    | Deterministic fingerprinting is used only for duplicate protection.
    | The real broker account number remains encrypted at rest.
    */
    'fingerprint_key' =>
        env('TRADEX_COPY_TRADE_FINGERPRINT_KEY')
        ?: env('APP_KEY'),

    'rate_limits' => [
        'read_per_minute' => 90,
        'action_per_minute' => 20,
        'submit_per_minute' => 10,
    ],
];
