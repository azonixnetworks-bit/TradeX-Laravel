<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ADMIN-14 — Safe Runtime Setting Registry
    |--------------------------------------------------------------------------
    |
    | Only explicitly allowlisted, non-secret operational values may be stored
    | in the system_settings table. This registry must never be expanded to
    | include credentials, API keys, private storage paths, payment
    | destinations, APP_KEY, cryptographic secrets or raw .env values.
    |
    */

    'general' => [
        'general.platform_name' => [
            'field' => 'platform_name',
            'type' => 'STRING',
            'default' => 'TradeX OS',
        ],

        'general.support_email' => [
            'field' => 'support_email',
            'type' => 'STRING',
            'default' => null,
        ],

        'general.support_whatsapp' => [
            'field' => 'support_whatsapp',
            'type' => 'STRING',
            'default' => null,
        ],

        'general.maintenance_notice_enabled' => [
            'field' => 'maintenance_notice_enabled',
            'type' => 'BOOL',
            'default' => false,
        ],

        'general.maintenance_notice' => [
            'field' => 'maintenance_notice',
            'type' => 'STRING',
            'default' => null,
        ],
    ],
];
