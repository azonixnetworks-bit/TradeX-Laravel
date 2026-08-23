<?php

return [
    'default_per_page' =>
        (int) env(
            'TRADEX_AFFILIATE_API_PER_PAGE',
            20
        ),

    'max_per_page' =>
        (int) env(
            'TRADEX_AFFILIATE_API_MAX_PER_PAGE',
            50
        ),

    'referral_base_url' =>
        env(
            'TRADEX_AFFILIATE_BASE_URL',
            env(
                'APP_URL'
            )
        ),

    'rate_limits' => [
        'read_per_minute' =>
            (int) env(
                'TRADEX_AFFILIATE_READ_PER_MINUTE',
                120
            ),

        'share_per_minute' =>
            (int) env(
                'TRADEX_AFFILIATE_SHARE_PER_MINUTE',
                20
            ),
    ],
];
