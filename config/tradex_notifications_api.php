<?php

return [
    'default_per_page' =>
        (int) env(
            'TRADEX_NOTIFICATIONS_PER_PAGE',
            20
        ),

    'max_per_page' =>
        (int) env(
            'TRADEX_NOTIFICATIONS_MAX_PER_PAGE',
            50
        ),

    'rate_limits' => [
        'read_per_minute' =>
            (int) env(
                'TRADEX_NOTIFICATIONS_READ_PER_MINUTE',
                120
            ),

        'write_per_minute' =>
            (int) env(
                'TRADEX_NOTIFICATIONS_WRITE_PER_MINUTE',
                30
            ),
    ],
];
