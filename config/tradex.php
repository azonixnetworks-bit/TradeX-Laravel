<?php

return [

    'super_admin' => [

        'name' => env(
            'SUPER_ADMIN_NAME',
            'TradeX Super Admin'
        ),

        'email' => env(
            'SUPER_ADMIN_EMAIL',
            'admin@tradex.local'
        ),

        'password' => env(
            'SUPER_ADMIN_PASSWORD'
        ),

    ],

    /*
    |--------------------------------------------------------------------------
    | TradeX Customer Response Performance
    |--------------------------------------------------------------------------
    |
    | Keep customer-facing aggregate responses briefly cached to avoid
    | repeating expensive relational queries during rapid refreshes. The TTL
    | stays intentionally short because account, learning and affiliate data
    | can change while the user is active.
    |
    */

    'performance' => [
        'dashboard_cache_seconds' => max(
            0,
            (int) env('TRADEX_DASHBOARD_CACHE_SECONDS', 10)
        ),

        'admin_stats_cache_seconds' => max(
            0,
            (int) env('TRADEX_ADMIN_STATS_CACHE_SECONDS', 30)
        ),

        'trade_ideas_list_cache_seconds' => max(
            0,
            (int) env('TRADEX_TRADE_IDEAS_LIST_CACHE_SECONDS', 10)
        ),

        'our_channel_list_cache_seconds' => max(
            0,
            (int) env('TRADEX_OUR_CHANNEL_LIST_CACHE_SECONDS', 30)
        ),

        'payment_methods_cache_seconds' => max(
            0,
            (int) env('TRADEX_PAYMENT_METHODS_CACHE_SECONDS', 30)
        ),

        'notification_summary_cache_seconds' => max(
            0,
            (int) env('TRADEX_NOTIFICATION_SUMMARY_CACHE_SECONDS', 5)
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | ADMIN-03A — Protected Video Upload + HLS Processing
    |--------------------------------------------------------------------------
    |
    | Source uploads, generated HLS manifests/segments and thumbnails remain
    | on the protected filesystem disk selected by the admin workflow.
    |
    | Playback authorization / short-lived learner playback URLs are a later
    | delivery concern and are intentionally not exposed here.
    |
    */

    'media' => [

        'queue' => env(
            'TRADEX_VIDEO_QUEUE',
            'video-processing'
        ),

        /*
        | Laravel's file max rule is measured in KiB.
        | 4,194,304 KiB = 4 GiB.
        |
        | PHP's upload_max_filesize and post_max_size must also be configured
        | high enough on the web server/PHP installation.
        */
        'max_upload_kb' => (int) env(
            'TRADEX_VIDEO_MAX_UPLOAD_KB',
            4194304
        ),

        'processing_timeout_seconds' => (int) env(
            'TRADEX_VIDEO_PROCESSING_TIMEOUT_SECONDS',
            7200
        ),

        /*
        | Leave blank to use:
        | storage/app/private/.processing/videos
        */
        'working_directory' => env(
            'TRADEX_VIDEO_WORK_DIR',
            ''
        ),

        'ffmpeg_binary' => env(
            'FFMPEG_BINARY',
            'ffmpeg'
        ),

        'ffprobe_binary' => env(
            'FFPROBE_BINARY',
            'ffprobe'
        ),

        'ffmpeg_preset' => env(
            'TRADEX_VIDEO_FFMPEG_PRESET',
            'superfast'
        ),

        'ffmpeg_crf' => (int) env(
            'TRADEX_VIDEO_FFMPEG_CRF',
            21
        ),

        'ffmpeg_threads' => max(
            1,
            (int) env(
                'TRADEX_VIDEO_FFMPEG_THREADS',
                2
            )
        ),

        'progress_update_seconds' => max(
            1,
            (int) env(
                'TRADEX_VIDEO_PROGRESS_UPDATE_SECONDS',
                2
            )
        ),

        'enabled_hls_variants' => array_values(
            array_filter(
                array_map(
                    static fn (string $value): string => trim($value),
                    explode(
                        ',',
                        (string) env(
                            'TRADEX_VIDEO_HLS_VARIANTS',
                            '360p,720p,1080p'
                        )
                    )
                ),
                static fn (string $value): bool => $value !== ''
            )
        ),

        'hls_segment_seconds' => (int) env(
            'TRADEX_VIDEO_HLS_SEGMENT_SECONDS',
            6
        ),

        /*
        | Renditions are generated only when the source is at least this tall.
        | The worker never intentionally upscales a source.
        */
        'hls_variants' => [
            [
                'name' => '360p',
                'height' => 360,
                'video_bitrate' => '800k',
                'audio_bitrate' => '96k',
                'bandwidth' => 1000000,
                'average_bandwidth' => 896000,
            ],
            [
                'name' => '480p',
                'height' => 480,
                'video_bitrate' => '1400k',
                'audio_bitrate' => '128k',
                'bandwidth' => 1700000,
                'average_bandwidth' => 1528000,
            ],
            [
                'name' => '720p',
                'height' => 720,
                'video_bitrate' => '2800k',
                'audio_bitrate' => '128k',
                'bandwidth' => 3200000,
                'average_bandwidth' => 2928000,
            ],
            [
                'name' => '1080p',
                'height' => 1080,
                'video_bitrate' => '5000k',
                'audio_bitrate' => '160k',
                'bandwidth' => 5700000,
                'average_bandwidth' => 5160000,
            ],
        ],

    ],

];
