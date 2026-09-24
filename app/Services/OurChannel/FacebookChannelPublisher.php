<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class FacebookChannelPublisher
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
        $runtime = $this->connections
            ->metaRuntime();

        $config = [
            'enabled' =>
                (bool) (
                    $runtime['facebook_enabled']
                    ?? false
                ),
            'graph_base_url' =>
                $runtime['graph_base_url']
                ?? 'https://graph.facebook.com',
            'graph_version' =>
                $runtime['graph_version']
                ?? 'v26.0',
            'page_access_token' =>
                $runtime['page_access_token']
                ?? null,
            'timeout_seconds' =>
                $runtime['facebook_timeout_seconds']
                ?? 90,
        ];

        $this->assertConfigured($config);

        $base = rtrim(
            (string) $config['graph_base_url'],
            '/'
        );
        $version = trim(
            (string) $config['graph_version'],
            '/'
        );
        $token = trim(
            (string) $config['page_access_token']
        );
        $timeout = max(
            10,
            (int) ($config['timeout_seconds'] ?? 90)
        );

        $endpoint = $base.'/'.$version.'/me/video_reels';

        $start = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->asForm()
            ->post(
                $endpoint,
                [
                    'upload_phase' => 'start',
                ]
            );

        $startJson = $start->json();
        $videoId = is_array($startJson)
            ? trim((string) ($startJson['video_id'] ?? ''))
            : '';
        $uploadUrl = is_array($startJson)
            ? trim((string) ($startJson['upload_url'] ?? ''))
            : '';

        if (
            ! $start->successful()
            || $videoId === ''
            || $uploadUrl === ''
        ) {
            throw $this->providerFailure(
                $start,
                'UPLOAD_INIT_FAILED',
                'Facebook rejected the Reels upload initialization.'
            );
        }

        $upload = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'Authorization' => 'OAuth '.$token,
                'file_url' => $this->source->signedUrl($video),
            ])
            ->post($uploadUrl);

        $uploadJson = $upload->json();

        if (
            ! $upload->successful()
            || ! is_array($uploadJson)
            || ($uploadJson['success'] ?? false) !== true
        ) {
            throw $this->providerFailure(
                $upload,
                'UPLOAD_FAILED',
                'Facebook rejected the hosted Reel upload.'
            );
        }

        $finish = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->asForm()
            ->post(
                $endpoint,
                [
                    'video_id' => $videoId,
                    'upload_phase' => 'finish',
                    'video_state' => 'PUBLISHED',
                    'description' => $this->description($video),
                    'title' => Str::limit(
                        $video->title,
                        255,
                        ''
                    ),
                ]
            );

        $finishJson = $finish->json();

        if (
            ! $finish->successful()
            || ! is_array($finishJson)
            || ($finishJson['success'] ?? false) !== true
        ) {
            throw $this->providerFailure(
                $finish,
                'PUBLISH_FAILED',
                'Facebook rejected the Reel publication.'
            );
        }

        return [
            'provider_post_id' => $videoId,
            'provider_url' => $this->permalink(
                $base,
                $version,
                $videoId,
                $token,
                $timeout
            ),
            'response_meta' => [
                'video_id' => $videoId,
            ],
        ];
    }

    private function permalink(
        string $base,
        string $version,
        string $videoId,
        string $token,
        int $timeout
    ): ?string {
        $response = Http::timeout($timeout)
            ->withToken($token)
            ->acceptJson()
            ->get(
                $base.'/'.$version.'/'.$videoId,
                [
                    'fields' => 'permalink_url',
                ]
            );

        $json = $response->json();
        $url = is_array($json)
            ? trim((string) ($json['permalink_url'] ?? ''))
            : '';

        return $response->successful() && $url !== ''
            ? $url
            : null;
    }

    private function description(ChannelVideo $video): string
    {
        return Str::limit(
            trim(
                implode(
                    PHP_EOL.PHP_EOL,
                    array_filter([
                        trim((string) $video->description),
                        trim((string) $video->hashtags),
                    ])
                )
            ),
            5000,
            ''
        );
    }

    private function assertConfigured(array $config): void
    {
        foreach (
            [
                'graph_base_url',
                'graph_version',
                'page_access_token',
            ]
            as $key
        ) {
            if (
                ! ($config['enabled'] ?? false)
                || trim((string) ($config[$key] ?? '')) === ''
            ) {
                throw new ChannelPublicationException(
                    ChannelVideoPublication::PLATFORM_FACEBOOK,
                    'NOT_CONFIGURED',
                    'Facebook publishing is not configured.'
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
            ChannelVideoPublication::PLATFORM_FACEBOOK,
            (string) $code,
            $safeMessage
        );
    }
}
