<?php

return [
    'courses_per_page' =>
        (int) env(
            'TRADEX_EDU_COURSES_PER_PAGE',
            20
        ),

    'courses_max_per_page' =>
        (int) env(
            'TRADEX_EDU_COURSES_MAX_PER_PAGE',
            50
        ),

    'localization' => [
        /*
        | language_code remains the source-language course filter.
        | locale controls only the requested display language.
        */
        'query_parameter' => 'locale',
        'header' => 'X-TradeX-Language',
    ],

    'playback' => [
        'token_minutes' =>
            (int) env(
                'TRADEX_EDU_PLAYBACK_TOKEN_MINUTES',
                5
            ),

        'max_asset_requests' =>
            (int) env(
                'TRADEX_EDU_PLAYBACK_MAX_ASSET_REQUESTS',
                1000
            ),

        'bind_user_agent' =>
            (bool) env(
                'TRADEX_EDU_PLAYBACK_BIND_USER_AGENT',
                false
            ),

        'allow_local_streaming' =>
            (bool) env(
                'TRADEX_EDU_ALLOW_LOCAL_STREAMING',
                true
            ),
    ],

    'assignments' => [
        /*
        | E.164-style digits only is recommended, e.g. 9477XXXXXXX.
        | Leave empty to open WhatsApp share mode without a fixed recipient.
        */
        'whatsapp_recipient' =>
            env(
                'TRADEX_EDU_ASSIGNMENT_WHATSAPP_NUMBER'
            ),
    ],

    'rate_limits' => [
        'read_per_minute' => 120,
        'progress_per_minute' => 30,
        'token_per_minute' => 20,
        'playback_asset_per_minute' => 600,
        'assignment_action_per_minute' => 20,
        'assignment_material_per_minute' => 30,
    ],
];
