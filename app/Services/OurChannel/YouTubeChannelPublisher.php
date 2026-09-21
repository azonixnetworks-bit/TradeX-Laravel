<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class YouTubeChannelPublisher
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
            ->youtubeRuntime();

        $this->assertConfigured($config);

        $token = $this->accessToken($config);
        $size = $this->source->size($video);
        $mime = $this->source->mime($video);
        $timeout = max(
            30,
            (int) ($config['timeout_seconds'] ?? 1800)
        );

        $snippet = [
            'title' => mb_substr($video->title, 0, 100),
            'description' => mb_substr(
                $this->description($video),
                0,
                5000
            ),
            'categoryId' => (string) (
                $config['category_id']
                ?? '27'
            ),
        ];

        $tags = $this->tags($video);

        if ($tags !== []) {
            $snippet['tags'] = $tags;
        }

        $privacy = strtolower(
            trim(
                (string) (
                    $config['privacy_status']
                    ?? 'private'
                )
            )
        );

        if (! in_array(
            $privacy,
            [
                'private',
                'unlisted',
                'public',
            ],
            true
        )) {
            $privacy = 'private';
        }

        $endpoint = rtrim(
            (string) $config['upload_url'],
            '?'
        ).'?'.http_build_query([
            'uploadType' => 'resumable',
            'part' => 'snippet,status',
        ]);

        $init = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $size,
                'X-Upload-Content-Type' => $mime,
            ])
            ->post(
                $endpoint,
                [
                    'snippet' => $snippet,
                    'status' => [
                        'privacyStatus' => $privacy,
                        'selfDeclaredMadeForKids' => false,
                    ],
                ]
            );

        if (! $init->successful()) {
            throw $this->providerFailure(
                $init,
                'UPLOAD_INIT_FAILED',
                'YouTube rejected the resumable upload initialization.'
            );
        }

        $location = trim(
            (string) $init->header('Location')
        );

        if ($location === '') {
            throw new ChannelPublicationException(
                ChannelVideoPublication::PLATFORM_YOUTUBE,
                'UPLOAD_LOCATION_MISSING',
                'YouTube did not return a resumable upload location.'
            );
        }

        $stream = $this->source->open($video);

        try {
            $upload = Http::timeout($timeout)
                ->withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'Content-Length' => (string) $size,
                ])
                ->withBody($stream, $mime)
                ->send(
                    'PUT',
                    $location
                );
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $json = $upload->json();
        $videoId = is_array($json)
            ? trim((string) ($json['id'] ?? ''))
            : '';

        if (! $upload->successful() || $videoId === '') {
            throw $this->providerFailure(
                $upload,
                'UPLOAD_FAILED',
                'YouTube rejected the master video upload.'
            );
        }

        return [
            'provider_post_id' => $videoId,
            'provider_url' => 'https://www.youtube.com/watch?v='.$videoId,
            'response_meta' => [
                'privacy_status' => $privacy,
                'kind' => is_array($json)
                    ? ($json['kind'] ?? null)
                    : null,
            ],
        ];
    }

    private function accessToken(array $config): string
    {
        $response = Http::timeout(30)
            ->asForm()
            ->post(
                (string) $config['token_url'],
                [
                    'client_id' => (string) $config['client_id'],
                    'client_secret' => (string) $config['client_secret'],
                    'refresh_token' => (string) $config['refresh_token'],
                    'grant_type' => 'refresh_token',
                ]
            );

        $json = $response->json();
        $token = is_array($json)
            ? trim((string) ($json['access_token'] ?? ''))
            : '';

        if (! $response->successful() || $token === '') {
            throw new ChannelPublicationException(
                ChannelVideoPublication::PLATFORM_YOUTUBE,
                'OAUTH_REFRESH_FAILED',
                'YouTube OAuth access could not be refreshed.'
            );
        }

        return $token;
    }

    private function assertConfigured(array $config): void
    {
        $required = [
            'token_url',
            'upload_url',
            'client_id',
            'client_secret',
            'refresh_token',
        ];

        foreach ($required as $key) {
            if (
                ! ($config['enabled'] ?? false)
                || trim((string) ($config[$key] ?? '')) === ''
            ) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_YOUTUBE,
                    'NOT_CONFIGURED',
                    'YouTube publishing is not configured.'
                );
            }
        }
    }

    private function description(ChannelVideo $video): string
    {
        return trim(
            implode(
                PHP_EOL.PHP_EOL,
                array_filter([
                    trim((string) $video->description),
                    trim((string) $video->hashtags),
                ])
            )
        );
    }

    private function tags(ChannelVideo $video): array
    {
        $hashtags = (string) $video->hashtags;

        preg_match_all(
            '/#([\pL\pN_]+)/u',
            $hashtags,
            $matches
        );

        $tags = array_values(
            array_unique(
                array_map(
                    static fn (string $tag): string => mb_substr(
                        $tag,
                        0,
                        30
                    ),
                    $matches[1] ?? []
                )
            )
        );

        return array_slice($tags, 0, 25);
    }

    private function providerFailure(
        Response $response,
        string $fallbackCode,
        string $safeMessage
    ): ChannelPublicationException {
        $json = $response->json();

        $code = is_array($json)
            ? (
                $json['error']['errors'][0]['reason']
                ?? $json['error']['code']
                ?? $fallbackCode
            )
            : $fallbackCode;

        return new ChannelPublicationException(
            ChannelVideoPublication::PLATFORM_YOUTUBE,
            (string) $code,
            $safeMessage
        );
    }
}
