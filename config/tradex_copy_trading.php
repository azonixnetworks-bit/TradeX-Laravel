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
    | Deterministic fingerprinting is used only for duplicate protection.
    | The real broker account number remains encrypted at rest.
    |
    | A blank/absent dedicated key falls back to APP_KEY for local/dev
    | compatibility. Production should set a separate stable high-entropy key.
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
