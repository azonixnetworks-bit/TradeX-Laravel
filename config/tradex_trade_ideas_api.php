<?php

return [
    'per_page' =>
        (int) env(
            'TRADEX_TRADE_IDEAS_PER_PAGE',
            20
        ),

    'max_per_page' =>
        (int) env(
            'TRADEX_TRADE_IDEAS_MAX_PER_PAGE',
            50
        ),

    'rate_limits' => [
        'read_per_minute' =>
            (int) env(
                'TRADEX_TRADE_IDEAS_READ_PER_MINUTE',
                120
            ),

        'media_per_minute' =>
            (int) env(
                'TRADEX_TRADE_IDEAS_MEDIA_PER_MINUTE',
                60
            ),

        'share_per_minute' =>
            (int) env(
                'TRADEX_TRADE_IDEAS_SHARE_PER_MINUTE',
                20
            ),
    ],
];
