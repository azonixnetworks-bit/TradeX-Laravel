<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ADMIN-17 — Legal Policy Registry
    |--------------------------------------------------------------------------
    |
    | This registry defines document categories only. It does not contain
    | substantive legal wording. Production legal text must be reviewed and
    | approved by qualified counsel for the jurisdictions where TradeX operates.
    |
    */

    'policy_types' => [
        'TERMS_OF_SERVICE' => [
            'display_name' =>
                'Terms of Service',

            'audience' =>
                'ALL_USERS',
        ],

        'PRIVACY_POLICY' => [
            'display_name' =>
                'Privacy Policy',

            'audience' =>
                'ALL_USERS',
        ],

        'NO_REFUND_POLICY' => [
            'display_name' =>
                'No-Refund Policy',

            'audience' =>
                'PAYING_USERS',
        ],

        'TRADING_RISK_DISCLOSURE' => [
            'display_name' =>
                'Trading Risk Disclosure',

            'audience' =>
                'TRADING_USERS',
        ],

        'AFFILIATE_TERMS' => [
            'display_name' =>
                'Affiliate Program Terms',

            'audience' =>
                'AFFILIATES',
        ],

        'MARKETING_CONSENT' => [
            'display_name' =>
                'Marketing Consent Notice',

            'audience' =>
                'MARKETING_OPT_IN',
        ],

        'COMPLAINTS_POLICY' => [
            'display_name' =>
                'Complaints Policy',

            'audience' =>
                'ALL_USERS',
        ],
    ],

    'content_max_characters' =>
        200000,

    'summary_max_characters' =>
        500,

    /*
     * Legal publication datetime-local inputs are entered by administrators in
     * this business timezone, then normalized to the application's UTC clock
     * before persistence/comparison. Keep APP timezone/storage semantics UTC.
     */
    'admin_timezone' =>
        env(
            'TRADEX_ADMIN_TIMEZONE',
            'Asia/Colombo'
        ),

    /*
     * Current repository has no customer legal-acceptance API/ledger.
     * ADMIN-17 must not claim acceptance enforcement is wired.
     */
    'acceptance_runtime' =>
        'NOT_WIRED',
];
