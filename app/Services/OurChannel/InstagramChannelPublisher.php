<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InstagramChannelPublisher
{
    public function __construct(
        private readonly ChannelMediaSource $source
    ) {
    }

    public function publish(
        ChannelVideo $video,
        ChannelVideoPublication $publication
    ): array {
        $config = (array) config(
            'tradex_channels.platforms.instagram',
            []
        );

        $this->assertConfigured($config);

        $base = rtrim(
            (string) $config['graph_base_url'],
            '/'
        );
        $version = trim(
            (string) $config['graph_version'],
            '/'
        );
        $userId = trim(
            (string) $config['ig_user_id']
        );
        $token = trim(
            (string) $config['access_token']
        );
        $timeout = max(
            10,
            (int) ($config['timeout_seconds'] ?? 60)
        );

        $container = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->asForm()
            ->post(
                $base.'/'.$version.'/'.$userId.'/media',
                [
                    'media_type' => 'REELS',
                    'video_url' => $this->source->signedUrl($video),
                    'caption' => $this->caption($video),
                    'share_to_feed' => (
                        $config['share_to_feed']
                        ?? true
                    )
                        ? 'true'
                        : 'false',
                ]
            );

        $containerJson = $container->json();
        $containerId = is_array($containerJson)
            ? trim((string) ($containerJson['id'] ?? ''))
            : '';

        if (! $container->successful() || $containerId === '') {
            throw $this->providerFailure(
                $container,
                'CONTAINER_CREATE_FAILED',
                'Instagram rejected the Reels media container.'
            );
        }

        $this->waitUntilReady(
            $base,
            $version,
            $containerId,
            $token,
            $config,
            $timeout
        );

        $publish = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->asForm()
            ->post(
                $base.'/'.$version.'/'.$userId.'/media_publish',
                [
                    'creation_id' => $containerId,
                ]
            );

        $publishJson = $publish->json();
        $mediaId = is_array($publishJson)
            ? trim((string) ($publishJson['id'] ?? ''))
            : '';

        if (! $publish->successful() || $mediaId === '') {
            throw $this->providerFailure(
                $publish,
                'PUBLISH_FAILED',
                'Instagram rejected the Reel publication.'
            );
        }

        $permalink = $this->permalink(
            $base,
            $version,
            $mediaId,
            $token,
            $timeout
        );

        return [
            'provider_post_id' => $mediaId,
            'provider_url' => $permalink,
            'response_meta' => [
                'container_id' => $containerId,
                'share_to_feed' => (bool) (
                    $config['share_to_feed']
                    ?? true
                ),
            ],
        ];
    }

    private function waitUntilReady(
        string $base,
        string $version,
        string $containerId,
        string $token,
        array $config,
        int $timeout
    ): void {
        $attempts = max(
            3,
            (int) ($config['poll_attempts'] ?? 30)
        );
        $seconds = max(
            2,
            (int) ($config['poll_seconds'] ?? 5)
        );

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $response = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->get(
                    $base.'/'.$version.'/'.$containerId,
                    [
                        'fields' => 'status_code,status',
                    ]
                );

            $json = $response->json();

            if (! $response->successful()) {
                throw $this->providerFailure(
                    $response,
                    'STATUS_FAILED',
                    'Instagram could not report the Reel processing status.'
                );
            }

            $status = is_array($json)
                ? strtoupper(
                    trim(
                        (string) ($json['status_code'] ?? '')
                    )
                )
                : '';

            if ($status === 'FINISHED') {
                return;
            }

            if (in_array(
                $status,
                [
                    'ERROR',
                    'EXPIRED',
                ],
                true
            )) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_INSTAGRAM,
                    'MEDIA_PROCESSING_'.$status,
                    'Instagram could not finish processing the Reel.'
                );
            }

            if ($attempt < $attempts) {
                sleep($seconds);
            }
        }

        throw new ChannelPublicationException(
            ChannelVideoPublication::PLATFORM_INSTAGRAM,
            'PROCESSING_TIMEOUT',
            'Instagram Reel processing did not finish within the configured window.'
        );
    }

    private function permalink(
        string $base,
        string $version,
        string $mediaId,
        string $token,
        int $timeout
    ): ?string {
        $response = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->get(
                $base.'/'.$version.'/'.$mediaId,
                [
                    'fields' => 'permalink',
                ]
            );

        $json = $response->json();
        $url = is_array($json)
            ? trim((string) ($json['permalink'] ?? ''))
            : '';

        return $response->successful() && $url !== ''
            ? $url
            : null;
    }

    private function caption(ChannelVideo $video): string
    {
        return Str::limit(
            trim(
                implode(
                    PHP_EOL.PHP_EOL,
                    array_filter([
                        $video->title,
                        trim((string) $video->description),
                        trim((string) $video->hashtags),
                    ])
                )
            ),
            2150,
            ''
        );
    }

    private function assertConfigured(array $config): void
    {
        foreach (
            [
                'graph_base_url',
                'graph_version',
                'ig_user_id',
                'access_token',
            ]
            as $key
        ) {
            if (
                ! ($config['enabled'] ?? false)
                || trim((string) ($config[$key] ?? '')) === ''
            ) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_INSTAGRAM,
                    'NOT_CONFIGURED',
                    'Instagram publishing is not configured.'
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
                $json['error']['code']
                ?? $json['error']['type']
                ?? $fallbackCode
            )
            : $fallbackCode;

        return new ChannelPublicationException(
            ChannelVideoPublication::PLATFORM_INSTAGRAM,
            (string) $code,
            $safeMessage
        );
    }
}
