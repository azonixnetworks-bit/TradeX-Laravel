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
    | Deterministic fingerprinting is used only for duplicate protection.
    | The real broker account number remains encrypted at rest.
    */
    'fingerprint_key' => env(
        'TRADEX_COPY_TRADE_FINGERPRINT_KEY',
        env('APP_KEY')
    ),

    'rate_limits' => [
        'read_per_minute' => 90,
        'action_per_minute' => 20,
        'submit_per_minute' => 10,
    ],
];
