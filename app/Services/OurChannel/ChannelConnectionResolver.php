<?php

namespace App\Services\OurChannel;

use App\Models\ChannelConnection;
use App\Models\ChannelVideoPublication;

class ChannelConnectionResolver
{
    public function platformStates(): array
    {
        return [
            ChannelVideoPublication::PLATFORM_YOUTUBE => [
                'mode' => 'AUTO',
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_YOUTUBE
                ),
            ],
            ChannelVideoPublication::PLATFORM_INSTAGRAM => [
                'mode' => 'AUTO',
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_INSTAGRAM
                ),
            ],
            ChannelVideoPublication::PLATFORM_FACEBOOK => [
                'mode' => 'AUTO',
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_FACEBOOK
                ),
            ],
            ChannelVideoPublication::PLATFORM_TELEGRAM => [
                'mode' => 'AUTO',
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_TELEGRAM
                ),
            ],
            ChannelVideoPublication::PLATFORM_TIKTOK => [
                'mode' => 'CONFIRM',
                'enabled' => false,
            ],
            ChannelVideoPublication::PLATFORM_WHATSAPP_CHANNEL => [
                'mode' => 'MANUAL',
                'enabled' => false,
            ],
        ];
    }

    public function isEnabled(string $platform): bool
    {
        return match ($platform) {
            ChannelVideoPublication::PLATFORM_YOUTUBE =>
                (bool) $this->youtubeRuntime()['enabled'],

            ChannelVideoPublication::PLATFORM_INSTAGRAM =>
                (bool) $this->metaRuntime()['instagram_enabled'],

            ChannelVideoPublication::PLATFORM_FACEBOOK =>
                (bool) $this->metaRuntime()['facebook_enabled'],

            ChannelVideoPublication::PLATFORM_TELEGRAM =>
                (bool) $this->telegramRuntime()['enabled'],

            default =>
                false,
        };
    }


    public function metaRuntime(): array
    {
        $facebookConfig = (array) config(
            'tradex_channels.platforms.facebook',
            []
        );

        $instagramConfig = (array) config(
            'tradex_channels.platforms.instagram',
            []
        );

        $connection = ChannelConnection::query()
            ->where(
                'platform',
                ChannelConnection::PLATFORM_META
            )
            ->first();

        if (! $connection instanceof ChannelConnection) {
            return [
                'source' => 'ENV',
                'graph_base_url' =>
                    $facebookConfig['graph_base_url']
                    ?? $instagramConfig['graph_base_url']
                    ?? 'https://graph.facebook.com',
                'graph_version' =>
                    $facebookConfig['graph_version']
                    ?? $instagramConfig['graph_version']
                    ?? 'v26.0',
                'facebook_enabled' =>
                    (bool) (
                        $facebookConfig['enabled']
                        ?? false
                    ),
                'instagram_enabled' =>
                    (bool) (
                        $instagramConfig['enabled']
                        ?? false
                    ),
                'page_access_token' =>
                    $facebookConfig['page_access_token']
                    ?? $instagramConfig['access_token']
                    ?? null,
                'ig_user_id' =>
                    $instagramConfig['ig_user_id']
                    ?? null,
                'facebook_timeout_seconds' =>
                    $facebookConfig['timeout_seconds']
                    ?? 90,
                'instagram_timeout_seconds' =>
                    $instagramConfig['timeout_seconds']
                    ?? 60,
                'instagram_share_to_feed' =>
                    (bool) (
                        $instagramConfig['share_to_feed']
                        ?? true
                    ),
                'instagram_poll_attempts' =>
                    $instagramConfig['poll_attempts']
                    ?? 30,
                'instagram_poll_seconds' =>
                    $instagramConfig['poll_seconds']
                    ?? 5,
            ];
        }

        $credentials = is_array(
            $connection->credentials
        )
            ? $connection->credentials
            : [];

        $settings = is_array(
            $connection->settings
        )
            ? $connection->settings
            : [];

        $metadata = is_array(
            $connection->metadata
        )
            ? $connection->metadata
            : [];

        $verified =
            $connection->isTestedSuccessfully()
            && filled(
                $credentials['page_access_token']
                ?? null
            );

        $instagramId = trim(
            (string) (
                $metadata['instagram']['id']
                ?? ''
            )
        );

        return [
            'source' => 'DATABASE',
            'graph_base_url' =>
                $facebookConfig['graph_base_url']
                ?? $instagramConfig['graph_base_url']
                ?? 'https://graph.facebook.com',
            'graph_version' =>
                $settings['graph_version']
                ?? (
                    $facebookConfig['graph_version']
                    ?? 'v26.0'
                ),
            'page_access_token' =>
                $credentials['page_access_token']
                ?? null,
            'facebook_enabled' =>
                (bool) $connection->is_enabled
                && $verified
                && (bool) (
                    $settings['facebook_enabled']
                    ?? false
                ),
            'instagram_enabled' =>
                (bool) $connection->is_enabled
                && $verified
                && $instagramId !== ''
                && (bool) (
                    $settings['instagram_enabled']
                    ?? false
                ),
            'ig_user_id' =>
                $instagramId !== ''
                    ? $instagramId
                    : null,
            'facebook_timeout_seconds' =>
                $facebookConfig['timeout_seconds']
                ?? 90,
            'instagram_timeout_seconds' =>
                $instagramConfig['timeout_seconds']
                ?? 60,
            'instagram_share_to_feed' =>
                (bool) (
                    $instagramConfig['share_to_feed']
                    ?? true
                ),
            'instagram_poll_attempts' =>
                $instagramConfig['poll_attempts']
                ?? 30,
            'instagram_poll_seconds' =>
                $instagramConfig['poll_seconds']
                ?? 5,
            'last_test_status' =>
                $connection->last_test_status,
            'last_tested_at' =>
                $connection->last_tested_at,
        ];
    }

    public function youtubeRuntime(): array
    {
        $config = (array) config(
            'tradex_channels.platforms.youtube',
            []
        );

        $connection = ChannelConnection::query()
            ->where(
                'platform',
                ChannelConnection::PLATFORM_YOUTUBE
            )
            ->first();

        if (! $connection instanceof ChannelConnection) {
            return [
                ...$config,
                'source' => 'ENV',
                'enabled' => (bool) (
                    $config['enabled']
                    ?? false
                ),
            ];
        }

        $credentials = is_array($connection->credentials)
            ? $connection->credentials
            : [];

        $settings = is_array($connection->settings)
            ? $connection->settings
            : [];

        $tested = $connection->isTestedSuccessfully();

        return [
            ...$config,
            'source' => 'DATABASE',
            'enabled' =>
                (bool) $connection->is_enabled
                && $tested
                && filled(
                    $credentials['refresh_token']
                    ?? null
                ),
            'client_id' =>
                $credentials['client_id']
                ?? null,
            'client_secret' =>
                $credentials['client_secret']
                ?? null,
            'refresh_token' =>
                $credentials['refresh_token']
                ?? null,
            'privacy_status' =>
                $settings['privacy_status']
                ?? (
                    $config['privacy_status']
                    ?? 'private'
                ),
            'category_id' =>
                $settings['category_id']
                ?? (
                    $config['category_id']
                    ?? '27'
                ),
            'last_test_status' =>
                $connection->last_test_status,
            'last_tested_at' =>
                $connection->last_tested_at,
        ];
    }

    public function telegramRuntime(): array
    {
        $config = (array) config(
            'tradex_channels.platforms.telegram',
            []
        );

        $connection = ChannelConnection::query()
            ->where(
                'platform',
                ChannelConnection::PLATFORM_TELEGRAM
            )
            ->first();

        if (! $connection instanceof ChannelConnection) {
            return [
                ...$config,
                'source' => 'ENV',
                'enabled' => (bool) (
                    $config['enabled']
                    ?? false
                ),
            ];
        }

        $credentials = is_array($connection->credentials)
            ? $connection->credentials
            : [];

        $settings = is_array($connection->settings)
            ? $connection->settings
            : [];

        $tested = $connection->isTestedSuccessfully();

        return [
            ...$config,
            'source' => 'DATABASE',
            'enabled' =>
                (bool) $connection->is_enabled
                && $tested,
            'bot_token' =>
                $credentials['bot_token']
                ?? null,
            'chat_id' =>
                $credentials['chat_id']
                ?? null,
            'protect_content' =>
                (bool) (
                    $settings['protect_content']
                    ?? (
                        $config['protect_content']
                        ?? false
                    )
                ),
            'last_test_status' =>
                $connection->last_test_status,
            'last_tested_at' =>
                $connection->last_tested_at,
        ];
    }

    public function connectionOverview(): array
    {
        $meta = ChannelConnection::query()
            ->with('updatedByAdmin:id,name,email')
            ->where(
                'platform',
                ChannelConnection::PLATFORM_META
            )
            ->first();

        $youtube = ChannelConnection::query()
            ->with('updatedByAdmin:id,name,email')
            ->where(
                'platform',
                ChannelConnection::PLATFORM_YOUTUBE
            )
            ->first();

        $telegram = ChannelConnection::query()
            ->with('updatedByAdmin:id,name,email')
            ->where(
                'platform',
                ChannelConnection::PLATFORM_TELEGRAM
            )
            ->first();

        $metaRuntime = $this->metaRuntime();
        $youtubeRuntime = $this->youtubeRuntime();
        $telegramRuntime = $this->telegramRuntime();

        return [
            'meta' => [
                'exists' =>
                    $meta instanceof ChannelConnection,
                'version' => (int) (
                    $meta?->version
                    ?? 0
                ),
                'app_id_present' =>
                    filled(
                        is_array($meta?->credentials)
                            ? (
                                $meta->credentials['app_id']
                                ?? null
                            )
                            : null
                    ),
                'app_secret_present' =>
                    filled(
                        is_array($meta?->credentials)
                            ? (
                                $meta->credentials['app_secret']
                                ?? null
                            )
                            : null
                    ),
                'graph_version' =>
                    $metaRuntime['graph_version']
                    ?? 'v26.0',
                'facebook_enabled' =>
                    (bool) (
                        $metaRuntime['facebook_enabled']
                        ?? false
                    ),
                'instagram_enabled' =>
                    (bool) (
                        $metaRuntime['instagram_enabled']
                        ?? false
                    ),
                'control_facebook_enabled' =>
                    (bool) (
                        is_array($meta?->settings)
                            ? (
                                $meta->settings[
                                    'facebook_enabled'
                                ]
                                ?? false
                            )
                            : false
                    ),
                'control_instagram_enabled' =>
                    (bool) (
                        is_array($meta?->settings)
                            ? (
                                $meta->settings[
                                    'instagram_enabled'
                                ]
                                ?? false
                            )
                            : false
                    ),
                'last_test_status' =>
                    $meta?->last_test_status
                    ?? (
                        (
                            ($metaRuntime['facebook_enabled'] ?? false)
                            || ($metaRuntime['instagram_enabled'] ?? false)
                        )
                            ? ChannelConnection::TEST_SUCCESS
                            : ChannelConnection::TEST_UNTESTED
                    ),
                'last_test_message' =>
                    $meta?->last_test_message,
                'last_tested_at' =>
                    $meta?->last_tested_at,
                'metadata' =>
                    $this->safeMetaMetadata(
                        $meta?->metadata
                    ),
                'candidate_pages' =>
                    $this->safeMetaCandidatePages(
                        $meta?->metadata
                    ),
                'updated_by' =>
                    $meta?->updatedByAdmin?->name,
                'updated_at' =>
                    $meta?->updated_at,
            ],

            'youtube' => [
                'exists' =>
                    $youtube instanceof ChannelConnection,
                'version' => (int) (
                    $youtube?->version
                    ?? 0
                ),
                'enabled' => (bool) (
                    $youtubeRuntime['enabled']
                    ?? false
                ),
                'control_enabled' => (bool) (
                    $youtube?->is_enabled
                    ?? (
                        $youtubeRuntime['enabled']
                        ?? false
                    )
                ),
                'client_id_present' =>
                    filled(
                        $youtubeRuntime['client_id']
                        ?? null
                    ),
                'client_secret_present' =>
                    filled(
                        $youtubeRuntime['client_secret']
                        ?? null
                    ),
                'refresh_token_present' =>
                    filled(
                        $youtubeRuntime['refresh_token']
                        ?? null
                    ),
                'privacy_status' =>
                    $youtubeRuntime['privacy_status']
                    ?? 'private',
                'category_id' =>
                    (string) (
                        $youtubeRuntime['category_id']
                        ?? '27'
                    ),
                'source' =>
                    $youtubeRuntime['source']
                    ?? 'ENV',
                'last_test_status' =>
                    $youtube?->last_test_status
                    ?? (
                        ($youtubeRuntime['enabled'] ?? false)
                            ? ChannelConnection::TEST_SUCCESS
                            : ChannelConnection::TEST_UNTESTED
                    ),
                'last_test_message' =>
                    $youtube?->last_test_message,
                'last_tested_at' =>
                    $youtube?->last_tested_at,
                'metadata' =>
                    $this->safeYouTubeMetadata(
                        $youtube?->metadata
                    ),
                'updated_by' =>
                    $youtube?->updatedByAdmin?->name,
                'updated_at' =>
                    $youtube?->updated_at,
            ],

            'telegram' => [
                'exists' =>
                    $telegram instanceof ChannelConnection,
                'version' => (int) (
                    $telegram?->version
                    ?? 0
                ),
                'enabled' => (bool) (
                    $telegramRuntime['enabled']
                    ?? false
                ),
                'control_enabled' => (bool) (
                    $telegram?->is_enabled
                    ?? (
                        $telegramRuntime['enabled']
                        ?? false
                    )
                ),
                'token_present' =>
                    filled(
                        $telegramRuntime['bot_token']
                        ?? null
                    ),
                'chat_id_masked' =>
                    $this->maskChatId(
                        $telegramRuntime['chat_id']
                        ?? null
                    ),
                'protect_content' => (bool) (
                    $telegramRuntime['protect_content']
                    ?? false
                ),
                'source' =>
                    $telegramRuntime['source']
                    ?? 'ENV',
                'last_test_status' =>
                    $telegram?->last_test_status
                    ?? (
                        ($telegramRuntime['enabled'] ?? false)
                            ? ChannelConnection::TEST_SUCCESS
                            : ChannelConnection::TEST_UNTESTED
                    ),
                'last_test_message' =>
                    $telegram?->last_test_message,
                'last_tested_at' =>
                    $telegram?->last_tested_at,
                'metadata' =>
                    $this->safeTelegramMetadata(
                        $telegram?->metadata
                    ),
                'updated_by' =>
                    $telegram?->updatedByAdmin?->name,
                'updated_at' =>
                    $telegram?->updated_at,
            ],

            'instagram' => [
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_INSTAGRAM
                ),
                'managed_by' => 'META_OAUTH',
            ],

            'facebook' => [
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_FACEBOOK
                ),
                'managed_by' => 'META_OAUTH',
            ],

            'tiktok' => [
                'enabled' => false,
                'managed_by' => 'INTERACTIVE_CONSENT_REQUIRED',
            ],

            'whatsapp_channel' => [
                'enabled' => false,
                'managed_by' => 'MANUAL_API_PENDING',
            ],
        ];
    }


    private function safeMetaMetadata(
        mixed $metadata
    ): array {
        if (! is_array($metadata)) {
            return [];
        }

        $instagram = is_array(
            $metadata['instagram']
            ?? null
        )
            ? $metadata['instagram']
            : null;

        return [
            'page_id' =>
                $metadata['page_id']
                ?? null,
            'page_name' =>
                $metadata['page_name']
                ?? null,
            'instagram' => $instagram === null
                ? null
                : [
                    'id' =>
                        $instagram['id']
                        ?? null,
                    'username' =>
                        $instagram['username']
                        ?? null,
                    'name' =>
                        $instagram['name']
                        ?? null,
                    'profile_picture_url' =>
                        $instagram[
                            'profile_picture_url'
                        ]
                        ?? null,
                ],
            'granted_permissions' =>
                is_array(
                    $metadata['granted_permissions']
                    ?? null
                )
                    ? $metadata['granted_permissions']
                    : [],
        ];
    }

    private function safeMetaCandidatePages(
        mixed $metadata
    ): array {
        if (! is_array($metadata)) {
            return [];
        }

        $pages = is_array(
            $metadata['candidate_pages']
            ?? null
        )
            ? $metadata['candidate_pages']
            : [];

        return array_values(
            array_filter(
                array_map(
                    static function (mixed $page): ?array {
                        if (! is_array($page)) {
                            return null;
                        }

                        return [
                            'page_id' =>
                                $page['page_id']
                                ?? null,
                            'page_name' =>
                                $page['page_name']
                                ?? null,
                            'instagram' =>
                                is_array(
                                    $page['instagram']
                                    ?? null
                                )
                                    ? $page['instagram']
                                    : null,
                        ];
                    },
                    $pages
                )
            )
        );
    }

    private function safeYouTubeMetadata(
        mixed $metadata
    ): array {
        if (! is_array($metadata)) {
            return [];
        }

        return [
            'channel_id' =>
                $metadata['channel_id']
                ?? null,
            'channel_title' =>
                $metadata['channel_title']
                ?? null,
            'channel_handle' =>
                $metadata['channel_handle']
                ?? null,
            'thumbnail_url' =>
                $metadata['thumbnail_url']
                ?? null,
            'scopes' =>
                is_array(
                    $metadata['scopes']
                    ?? null
                )
                    ? $metadata['scopes']
                    : [],
        ];
    }

    private function safeTelegramMetadata(
        mixed $metadata
    ): array {
        if (! is_array($metadata)) {
            return [];
        }

        return [
            'bot_id' =>
                $metadata['bot_id']
                ?? null,
            'bot_username' =>
                $metadata['bot_username']
                ?? null,
            'bot_display_name' =>
                $metadata['bot_display_name']
                ?? null,
            'channel_id' =>
                $metadata['channel_id']
                ?? null,
            'channel_title' =>
                $metadata['channel_title']
                ?? null,
            'channel_username' =>
                $metadata['channel_username']
                ?? null,
            'member_status' =>
                $metadata['member_status']
                ?? null,
            'can_post_messages' =>
                (bool) (
                    $metadata['can_post_messages']
                    ?? false
                ),
        ];
    }

    private function configProviderEnabled(string $key): bool
    {
        return (bool) config(
            'tradex_channels.platforms.'
                .$key
                .'.enabled',
            false
        );
    }

    private function maskChatId(mixed $chatId): ?string
    {
        $chatId = trim((string) $chatId);

        if ($chatId === '') {
            return null;
        }

        if (str_starts_with($chatId, '@')) {
            return $chatId;
        }

        $length = mb_strlen($chatId);

        if ($length <= 6) {
            return str_repeat('•', $length);
        }

        return mb_substr($chatId, 0, 3)
            .str_repeat(
                '•',
                max(3, $length - 7)
            )
            .mb_substr($chatId, -4);
    }
}
