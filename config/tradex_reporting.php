<?php

return [
    /*
     * Cross-admin report export controls. One full leap year keeps normal
     * operational and annual reporting practical without allowing accidental
     * unbounded browser exports.
     */
    'max_range_days' => max(
        1,
        (int) env('TRADEX_REPORT_MAX_RANGE_DAYS', 366)
    ),

    'audit_timezone' => env(
        'TRADEX_ADMIN_TIMEZONE',
        'Asia/Colombo'
    ),
];
