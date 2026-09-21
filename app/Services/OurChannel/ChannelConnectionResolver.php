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
                $this->configProviderEnabled('instagram'),

            ChannelVideoPublication::PLATFORM_FACEBOOK =>
                $this->configProviderEnabled('facebook'),

            ChannelVideoPublication::PLATFORM_TELEGRAM =>
                (bool) $this->telegramRuntime()['enabled'],

            default =>
                false,
        };
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

        $youtubeRuntime = $this->youtubeRuntime();
        $telegramRuntime = $this->telegramRuntime();

        return [
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
                'managed_by' => 'SERVER_META_CONFIG',
            ],

            'facebook' => [
                'enabled' => $this->isEnabled(
                    ChannelVideoPublication::PLATFORM_FACEBOOK
                ),
                'managed_by' => 'SERVER_META_CONFIG',
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
