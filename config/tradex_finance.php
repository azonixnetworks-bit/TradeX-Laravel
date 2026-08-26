<?php

return [
    'timezone' => env(
        'TRADEX_FINANCE_TIMEZONE',
        'Asia/Colombo'
    ),

    'pagination' => [
        'max_per_page' => 100,
    ],

    'reconciliation_scan_limit' => 500,

    'daily_report' => [
        'enabled' => env(
            'TRADEX_FINANCE_DAILY_REPORT_ENABLED',
            false
        ),

        'owner_email' => env(
            'TRADEX_FINANCE_OWNER_REPORT_EMAIL'
        ),

        /*
         * Runs after midnight and reports the previous local business day,
         * avoiding an incomplete 23:59 end-of-day snapshot.
         */
        'time' => env(
            'TRADEX_FINANCE_DAILY_REPORT_TIME',
            '00:10'
        ),
    ],
];
