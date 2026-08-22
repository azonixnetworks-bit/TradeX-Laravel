<?php

$csv = static function (
    mixed $value
): array {
    return array_values(
        array_filter(
            array_map(
                static fn (string $item): string => trim($item),
                explode(
                    ',',
                    (string) $value
                )
            ),
            static fn (string $item): bool => $item !== ''
        )
    );
};

$bool = static function (
    mixed $value,
    bool $default = false
): bool {
    if ($value === null) {
        return $default;
    }

    return filter_var(
        $value,
        FILTER_VALIDATE_BOOL
    );
};

return [

    /*
    |--------------------------------------------------------------------------
    | ADMIN-05A — Trade Idea Distribution & Share Automation
    |--------------------------------------------------------------------------
    |
    | External provider credentials stay in environment variables.
    | Nothing sensitive is stored in this config file or database snapshots.
    |
    */

    'queue' => env(
        'TRADEX_TRADE_IDEA_DISTRIBUTION_QUEUE',
        'trade-ideas'
    ),

    'auto' => [
        /*
        | OFF by default to prevent accidental live external sending
        | immediately after deploying ADMIN-05A.
        */
        'on_publish' => $bool(
            env(
                'TRADEX_TRADE_IDEA_AUTO_ON_PUBLISH'
            ),
            false
        ),

        'on_update' => $bool(
            env(
                'TRADEX_TRADE_IDEA_AUTO_ON_UPDATE'
            ),
            false
        ),

        'on_close' => $bool(
            env(
                'TRADEX_TRADE_IDEA_AUTO_ON_CLOSE'
            ),
            false
        ),
    ],

    'jobs' => [
        'tries' => max(
            1,
            (int) env(
                'TRADEX_TRADE_IDEA_JOB_TRIES',
                3
            )
        ),

        'backoff_seconds' => [
            15,
            60,
            180,
        ],
    ],

    'telegram' => [
        'enabled' => $bool(
            env(
                'TRADEX_TELEGRAM_ENABLED'
            ),
            false
        ),

        'api_base_url' => rtrim(
            (string) env(
                'TRADEX_TELEGRAM_API_BASE_URL',
                'https://api.telegram.org'
            ),
            '/'
        ),

        'bot_token' => env(
            'TRADEX_TELEGRAM_BOT_TOKEN'
        ),

        /*
        | Examples:
        | @tradex_channel
        | -1001234567890
        */
        'chat_ids' => $csv(
            env(
                'TRADEX_TELEGRAM_CHAT_IDS',
                ''
            )
        ),

        'protect_content' => $bool(
            env(
                'TRADEX_TELEGRAM_PROTECT_CONTENT'
            ),
            true
        ),

        'timeout_seconds' => max(
            5,
            (int) env(
                'TRADEX_TELEGRAM_TIMEOUT_SECONDS',
                20
            )
        ),
    ],

    'whatsapp' => [
        'enabled' => $bool(
            env(
                'TRADEX_WHATSAPP_ENABLED'
            ),
            false
        ),

        'api_base_url' => rtrim(
            (string) env(
                'TRADEX_WHATSAPP_API_BASE_URL',
                'https://graph.facebook.com'
            ),
            '/'
        ),

        /*
        | Intentionally has no hard-coded Graph API version.
        | Set the currently supported version in deployment config.
        */
        'graph_version' => env(
            'TRADEX_WHATSAPP_GRAPH_VERSION'
        ),

        'phone_number_id' => env(
            'TRADEX_WHATSAPP_PHONE_NUMBER_ID'
        ),

        'access_token' => env(
            'TRADEX_WHATSAPP_ACCESS_TOKEN'
        ),

        /*
        | E.164 digits only, without spaces.
        | These are configured operational recipients, not arbitrary
        | request-provided destinations.
        */
        'recipients' => $csv(
            env(
                'TRADEX_WHATSAPP_RECIPIENTS',
                ''
            )
        ),

        /*
        | template = recommended production mode for business-initiated sends.
        | text     = free-form Cloud API message where policy/session allows.
        */
        'mode' => strtolower(
            trim(
                (string) env(
                    'TRADEX_WHATSAPP_MODE',
                    'template'
                )
            )
        ),

        /*
        | Template mode expects one BODY text variable {{1}} containing the
        | compact Trade Idea message.
        |
        | If an image template is configured and the idea has a JPEG/PNG chart,
        | ADMIN-05A uploads it to Cloud API and supplies it as HEADER image.
        */
        'template_text_name' => env(
            'TRADEX_WHATSAPP_TEMPLATE_TEXT_NAME'
        ),

        'template_image_name' => env(
            'TRADEX_WHATSAPP_TEMPLATE_IMAGE_NAME'
        ),

        'template_language' => env(
            'TRADEX_WHATSAPP_TEMPLATE_LANGUAGE',
            'en_US'
        ),

        'timeout_seconds' => max(
            5,
            (int) env(
                'TRADEX_WHATSAPP_TIMEOUT_SECONDS',
                25
            )
        ),
    ],

    'share' => [
        /*
        | Used only for personalized user-share payload/card generation.
        | Example:
        | https://app.tradex.example/register
        | -> https://app.tradex.example/register?ref=ABC123
        */
        'affiliate_base_url' => env(
            'TRADEX_AFFILIATE_BASE_URL',
            env(
                'APP_URL'
            )
        ),

        'card_disk' => env(
            'TRADEX_TRADE_IDEA_SHARE_CARD_DISK',
            'private'
        ),
    ],

];
