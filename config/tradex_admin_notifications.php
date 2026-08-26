<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ADMIN-15 — In-App Broadcast Fan-Out
    |--------------------------------------------------------------------------
    |
    | A "send" operation creates user_notification_receipts only.
    | This is the existing TradeX in-app notification inbox contract.
    | It is not FCM, email, SMS, Telegram or WhatsApp delivery.
    |
    */

    'insert_chunk_size' =>
        max(
            100,
            (int) env(
                'TRADEX_ADMIN_NOTIFICATION_INSERT_CHUNK',
                1000
            )
        ),

    /*
     * Safety ceiling for synchronous recipient snapshot creation.
     * Raise only after production database/load testing.
     */
    'max_recipients_per_broadcast' =>
        max(
            1,
            (int) env(
                'TRADEX_ADMIN_NOTIFICATION_MAX_RECIPIENTS',
                50000
            )
        ),

    'max_schedule_days' =>
        max(
            1,
            (int) env(
                'TRADEX_ADMIN_NOTIFICATION_MAX_SCHEDULE_DAYS',
                365
            )
        ),

    'default_per_page' =>
        20,

    'max_per_page' =>
        100,
];
