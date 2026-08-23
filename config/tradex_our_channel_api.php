<?php

return [
    'default_per_page' => max(
        1,
        (int) env(
            'TRADEX_OUR_CHANNEL_DEFAULT_PER_PAGE',
            20
        )
    ),

    'max_per_page' => max(
        1,
        (int) env(
            'TRADEX_OUR_CHANNEL_MAX_PER_PAGE',
            50
        )
    ),

    'rate_limits' => [
        'read_per_minute' => max(
            1,
            (int) env(
                'TRADEX_OUR_CHANNEL_READ_PER_MINUTE',
                120
            )
        ),
    ],
];
