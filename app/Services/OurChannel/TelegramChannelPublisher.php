<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TelegramChannelPublisher
{
    public function __construct(
        private readonly ChannelMediaSource $source,
        private readonly ChannelConnectionResolver $connections
    ) {
    }

    public function publish(
        ChannelVideo $video,
        ChannelVideoPublication $publication
    ): array {
        $config = $this->connections
            ->telegramRuntime();

        $this->assertConfigured($config);

        $size = $this->source->size($video);
        $max = max(
            1,
            (int) ($config['max_upload_bytes'] ?? 52428800)
        );

        if ($size > $max) {
            throw new ChannelPublicationException(
                ChannelVideoPublication::PLATFORM_TELEGRAM,
                'FILE_TOO_LARGE',
                'Telegram Bot API direct video upload is limited to 50 MB.'
            );
        }

        $token = trim((string) $config['bot_token']);
        $chatId = trim((string) $config['chat_id']);
        $base = rtrim(
            (string) $config['api_base_url'],
            '/'
        );
        $timeout = max(
            10,
            (int) ($config['timeout_seconds'] ?? 300)
        );

        $stream = $this->source->open($video);

        try {
            try {
                $response = Http::timeout($timeout)
                    ->attach(
                        'video',
                        $stream,
                        $this->source->filename($video),
                        [
                            'Content-Type' => $this->source->mime($video),
                        ]
                    )
                    ->post(
                        $base.'/bot'.$token.'/sendVideo',
                        [
                            'chat_id' => $chatId,
                            'caption' => $this->caption($video),
                            'supports_streaming' => 'true',
                            'protect_content' => (
                                $config['protect_content']
                                ?? false
                            )
                                ? 'true'
                                : 'false',
                        ]
                    );
            } catch (Throwable) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_TELEGRAM,
                    'NETWORK_ERROR',
                    'Telegram could not be reached for video publishing.'
                );
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $json = $response->json();
        $messageId = is_array($json)
            ? ($json['result']['message_id'] ?? null)
            : null;

        if (
            ! $response->successful()
            || ! is_array($json)
            || ($json['ok'] ?? false) !== true
            || $messageId === null
        ) {
            throw $this->providerFailure(
                $response,
                'SEND_FAILED',
                'Telegram rejected the channel video post.'
            );
        }

        return [
            'provider_post_id' => (string) $messageId,
            'provider_url' => $this->messageUrl(
                $chatId,
                (string) $messageId
            ),
            'response_meta' => [
                'message_id' => $messageId,
                'chat_id' => $json['result']['chat']['id'] ?? null,
            ],
        ];
    }

    private function caption(ChannelVideo $video): string
    {
        return Str::limit(
            trim(
                implode(
                    PHP_EOL.PHP_EOL,
                    array_filter([
                        $video->title,
                        trim((string) $video->short_description),
                        trim((string) $video->hashtags),
                    ])
                )
            ),
            1000,
            ''
        );
    }

    private function messageUrl(
        string $chatId,
        string $messageId
    ): ?string {
        if (! str_starts_with($chatId, '@')) {
            return null;
        }

        return 'https://t.me/'
            .ltrim($chatId, '@')
            .'/'
            .$messageId;
    }

    private function assertConfigured(array $config): void
    {
        foreach (
            [
                'api_base_url',
                'bot_token',
                'chat_id',
            ]
            as $key
        ) {
            if (
                ! ($config['enabled'] ?? false)
                || trim((string) ($config[$key] ?? '')) === ''
            ) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_TELEGRAM,
                    'NOT_CONFIGURED',
                    'Telegram Channel publishing is not configured.'
                );
            }
        }
    }

    private function providerFailure(
        Response $response,
        string $fallbackCode,
        string $safeMessage
    ): ChannelPublicationException {
        $json = $response->json();

        $code = is_array($json)
            ? (
                $json['error_code']
                ?? $fallbackCode
            )
            : $fallbackCode;

        return new ChannelPublicationException(
            ChannelVideoPublication::PLATFORM_TELEGRAM,
            (string) $code,
            $safeMessage
        );
    }
}
