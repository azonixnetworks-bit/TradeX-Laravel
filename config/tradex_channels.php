<?php

$bool = static function (mixed $value, bool $default = false): bool {
    if ($value === null) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOL);
};

return [
    'queue' => env(
        'TRADEX_CHANNEL_PUBLISH_QUEUE',
        'channel-publishing'
    ),

    'source_disk' => env(
        'TRADEX_CHANNEL_SOURCE_DISK',
        'private'
    ),

    'max_upload_kb' => max(
        1024,
        (int) env(
            'TRADEX_CHANNEL_MAX_UPLOAD_KB',
            2097152
        )
    ),

    'signed_media_minutes' => max(
        15,
        (int) env(
            'TRADEX_CHANNEL_SIGNED_MEDIA_MINUTES',
            180
        )
    ),

    'jobs' => [
        'tries' => max(
            1,
            (int) env(
                'TRADEX_CHANNEL_JOB_TRIES',
                1
            )
        ),

        'backoff_seconds' => [
            30,
            120,
            300,
        ],
    ],

    'platforms' => [
        'youtube' => [
            'authorization_url' => env(
                'TRADEX_CHANNEL_YOUTUBE_AUTHORIZATION_URL',
                'https://accounts.google.com/o/oauth2/v2/auth'
            ),
            'channels_url' => env(
                'TRADEX_CHANNEL_YOUTUBE_CHANNELS_URL',
                'https://www.googleapis.com/youtube/v3/channels'
            ),
            'scopes' => [
                'https://www.googleapis.com/auth/youtube.upload',
                'https://www.googleapis.com/auth/youtube.readonly',
            ],
            'enabled' => $bool(
                env('TRADEX_CHANNEL_YOUTUBE_ENABLED'),
                false
            ),
            'token_url' => env(
                'TRADEX_CHANNEL_YOUTUBE_TOKEN_URL',
                'https://oauth2.googleapis.com/token'
            ),
            'upload_url' => env(
                'TRADEX_CHANNEL_YOUTUBE_UPLOAD_URL',
                'https://www.googleapis.com/upload/youtube/v3/videos'
            ),
            'client_id' => env('TRADEX_CHANNEL_YOUTUBE_CLIENT_ID'),
            'client_secret' => env('TRADEX_CHANNEL_YOUTUBE_CLIENT_SECRET'),
            'refresh_token' => env('TRADEX_CHANNEL_YOUTUBE_REFRESH_TOKEN'),
            'privacy_status' => env(
                'TRADEX_CHANNEL_YOUTUBE_PRIVACY',
                'private'
            ),
            'category_id' => env(
                'TRADEX_CHANNEL_YOUTUBE_CATEGORY_ID',
                '27'
            ),
            'timeout_seconds' => max(
                30,
                (int) env(
                    'TRADEX_CHANNEL_YOUTUBE_TIMEOUT_SECONDS',
                    1800
                )
            ),
        ],

        'instagram' => [
            'enabled' => $bool(
                env('TRADEX_CHANNEL_INSTAGRAM_ENABLED'),
                false
            ),
            'graph_base_url' => env(
                'TRADEX_CHANNEL_META_GRAPH_BASE_URL',
                'https://graph.facebook.com'
            ),
            'graph_version' => env('TRADEX_CHANNEL_META_GRAPH_VERSION'),
            'ig_user_id' => env('TRADEX_CHANNEL_INSTAGRAM_USER_ID'),
            'access_token' => env('TRADEX_CHANNEL_INSTAGRAM_ACCESS_TOKEN'),
            'share_to_feed' => $bool(
                env('TRADEX_CHANNEL_INSTAGRAM_SHARE_TO_FEED'),
                true
            ),
            'poll_attempts' => max(
                3,
                (int) env(
                    'TRADEX_CHANNEL_INSTAGRAM_POLL_ATTEMPTS',
                    30
                )
            ),
            'poll_seconds' => max(
                2,
                (int) env(
                    'TRADEX_CHANNEL_INSTAGRAM_POLL_SECONDS',
                    5
                )
            ),
            'timeout_seconds' => max(
                10,
                (int) env(
                    'TRADEX_CHANNEL_INSTAGRAM_TIMEOUT_SECONDS',
                    60
                )
            ),
        ],

        'facebook' => [
            'enabled' => $bool(
                env('TRADEX_CHANNEL_FACEBOOK_ENABLED'),
                false
            ),
            'graph_base_url' => env(
                'TRADEX_CHANNEL_META_GRAPH_BASE_URL',
                'https://graph.facebook.com'
            ),
            'graph_version' => env('TRADEX_CHANNEL_META_GRAPH_VERSION'),
            'page_access_token' => env('TRADEX_CHANNEL_FACEBOOK_PAGE_ACCESS_TOKEN'),
            'timeout_seconds' => max(
                10,
                (int) env(
                    'TRADEX_CHANNEL_FACEBOOK_TIMEOUT_SECONDS',
                    90
                )
            ),
        ],

        'telegram' => [
            'enabled' => $bool(
                env('TRADEX_CHANNEL_TELEGRAM_ENABLED'),
                false
            ),
            'api_base_url' => env(
                'TRADEX_CHANNEL_TELEGRAM_API_BASE_URL',
                'https://api.telegram.org'
            ),
            'bot_token' => env('TRADEX_CHANNEL_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TRADEX_CHANNEL_TELEGRAM_CHAT_ID'),
            'protect_content' => $bool(
                env('TRADEX_CHANNEL_TELEGRAM_PROTECT_CONTENT'),
                false
            ),
            'timeout_seconds' => max(
                10,
                (int) env(
                    'TRADEX_CHANNEL_TELEGRAM_TIMEOUT_SECONDS',
                    300
                )
            ),
            'max_upload_bytes' => 52428800,
        ],

        /*
        | TikTok Direct Post requires a creator-info screen, a manually selected
        | privacy option and explicit creator consent for each post. It is kept
        | as ACTION_REQUIRED until that interactive OAuth/consent flow is used.
        */
        'tiktok' => [
            'mode' => 'interactive_required',
        ],

        /*
        | Meta currently documents WhatsApp Business Platform messaging, but
        | TradeX does not assume an undocumented WhatsApp Channel publishing API.
        */
        'whatsapp_channel' => [
            'mode' => 'manual',
        ],
    ],
];
