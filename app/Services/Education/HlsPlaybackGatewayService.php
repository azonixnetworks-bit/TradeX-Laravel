<?php

namespace App\Services\Education;

use App\Exceptions\AuthApiException;
use App\Models\EducationPlaybackSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HlsPlaybackGatewayService
{
    public function serve(
        EducationPlaybackSession $session,
        string $plainToken,
        ?string $requestedPath
    ): Response|StreamedResponse|RedirectResponse {
        $video =
            $session->video;

        $masterPath =
            trim(
                (string)
                    $video
                        ->hls_master_path
            );

        if ($masterPath === '') {
            throw new AuthApiException(
                409,
                'HLS_STREAM_UNAVAILABLE',
                'No HLS stream is available for this video.'
            );
        }

        $diskName =
            trim(
                (string)
                    $video
                        ->storage_disk
            );

        if ($diskName === '') {
            throw new AuthApiException(
                409,
                'VIDEO_STORAGE_UNAVAILABLE',
                'Video storage is not configured.'
            );
        }

        $masterDir =
            trim(
                str_replace(
                    '\\',
                    '/',
                    dirname(
                        $masterPath
                    )
                ),
                './'
            );

        $relativePath =
            $requestedPath !== null
            && trim($requestedPath) !== ''
                ? $this->normalizeRelativePath(
                    $requestedPath
                )
                : basename(
                    $masterPath
                );

        $objectPath =
            $masterDir !== ''
                ? $masterDir
                    .'/'
                    .$relativePath
                : $relativePath;

        $disk =
            Storage::disk(
                $diskName
            );

        if (! $disk->exists(
            $objectPath
        )) {
            throw new AuthApiException(
                404,
                'PLAYBACK_ASSET_NOT_FOUND',
                'The requested playback asset was not found.'
            );
        }

        if (
            str_ends_with(
                strtolower(
                    $relativePath
                ),
                '.m3u8'
            )
        ) {
            $contents = $this->manifestContents(
                $disk,
                $diskName,
                $objectPath
            );

            $rewritten =
                $this->rewriteManifest(
                    $contents,
                    $plainToken,
                    $relativePath
                );

            return response(
                $rewritten,
                200,
                [
                    'Content-Type' =>
                        'application/vnd.apple.mpegurl',

                    'Cache-Control' =>
                        'private, no-store, max-age=0',

                    'X-Content-Type-Options' =>
                        'nosniff',
                ]
            );
        }

        $driver =
            config(
                "filesystems.disks.{$diskName}.driver"
            );

        if ($driver === 's3') {
            $expires =
                now()->addSeconds(
                    max(
                        30,
                        min(
                            120,
                            now()->diffInSeconds(
                                $session
                                    ->expires_at,
                                false
                            )
                        )
                    )
                );

            try {
                $url =
                    $disk->temporaryUrl(
                        $objectPath,
                        $expires
                    );

                return redirect()
                    ->away(
                        $url
                    );
            } catch (\Throwable) {
                // Fall through to protected application streaming.
            }
        }

        if (
            ! (bool) config(
                'tradex_education.playback.allow_local_streaming',
                true
            )
        ) {
            throw new AuthApiException(
                503,
                'LOCAL_PLAYBACK_DISABLED',
                'Local playback streaming is disabled.'
            );
        }

        $stream =
            $disk->readStream(
                $objectPath
            );

        if (! is_resource(
            $stream
        )) {
            throw new AuthApiException(
                500,
                'PLAYBACK_STREAM_FAILED',
                'The playback asset could not be opened.'
            );
        }

        return response()->stream(
            function () use (
                $stream
            ): void {
                fpassthru(
                    $stream
                );

                fclose(
                    $stream
                );
            },
            200,
            [
                'Content-Type' =>
                    $this->contentType(
                        $relativePath
                    ),

                'Cache-Control' =>
                    $this->segmentCacheControl(),

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    private function manifestContents(
        mixed $disk,
        string $diskName,
        string $objectPath
    ): string {
        $seconds = (int) config(
            'tradex_education.playback.manifest_cache_seconds',
            60
        );

        if ($seconds <= 0) {
            return $disk->get($objectPath);
        }

        $key = 'education:hls:manifest:' . hash(
            'sha256',
            $diskName . '|' . $objectPath . '|' . $disk->lastModified($objectPath)
        );

        return Cache::store('file')->remember(
            $key,
            now()->addSeconds($seconds),
            fn (): string => $disk->get($objectPath)
        );
    }

    private function segmentCacheControl(): string
    {
        $seconds = (int) config(
            'tradex_education.playback.segment_browser_cache_seconds',
            60
        );

        return $seconds > 0
            ? 'private, max-age=' . $seconds
            : 'private, no-store, max-age=0';
    }

    private function rewriteManifest(
        string $contents,
        string $plainToken,
        string $currentPath
    ): string {
        $lines =
            preg_split(
                '/\r\n|\r|\n/',
                $contents
            );

        if ($lines === false) {
            return $contents;
        }

        $rewritten = [];

        foreach ($lines as $line) {
            $trimmed =
                trim(
                    $line
                );

            if ($trimmed === '') {
                $rewritten[] =
                    $line;

                continue;
            }

            if (
                str_starts_with(
                    $trimmed,
                    '#'
                )
            ) {
                $rewritten[] =
                    preg_replace_callback(
                        '/URI="([^"]+)"/',
                        function (
                            array $matches
                        ) use (
                            $plainToken,
                            $currentPath
                        ): string {
                            return 'URI="'
                                .$this->gatewayUrl(
                                    $plainToken,
                                    $this->resolveReference(
                                        $currentPath,
                                        $matches[1]
                                    )
                                )
                                .'"';
                        },
                        $line
                    ) ?? $line;

                continue;
            }

            $rewritten[] =
                $this->gatewayUrl(
                    $plainToken,
                    $this->resolveReference(
                        $currentPath,
                        $trimmed
                    )
                );
        }

        return implode(
            "\n",
            $rewritten
        );
    }

    private function resolveReference(
        string $currentPath,
        string $reference
    ): string {
        if (
            preg_match(
                '#^[a-z][a-z0-9+\-.]*://#i',
                $reference
            )
        ) {
            throw new AuthApiException(
                409,
                'EXTERNAL_HLS_REFERENCE_BLOCKED',
                'External HLS asset references are not allowed.'
            );
        }

        $pathOnly =
            parse_url(
                $reference,
                PHP_URL_PATH
            );

        if (! is_string(
            $pathOnly
        )) {
            throw new AuthApiException(
                409,
                'INVALID_HLS_REFERENCE',
                'The HLS asset reference is invalid.'
            );
        }

        $base =
            dirname(
                $currentPath
            );

        $combined =
            $base === '.'
                ? $pathOnly
                : $base
                    .'/'
                    .$pathOnly;

        return $this->normalizeRelativePath(
            $combined
        );
    }

    private function normalizeRelativePath(
        string $path
    ): string {
        $decoded =
            rawurldecode(
                $path
            );

        if (
            str_contains(
                $decoded,
                "\0"
            )
            || str_starts_with(
                $decoded,
                '/'
            )
        ) {
            throw new AuthApiException(
                400,
                'INVALID_PLAYBACK_ASSET_PATH',
                'The playback asset path is invalid.'
            );
        }

        $segments = [];

        foreach (
            explode(
                '/',
                str_replace(
                    '\\',
                    '/',
                    $decoded
                )
            ) as $segment
        ) {
            if (
                $segment === ''
                || $segment === '.'
            ) {
                continue;
            }

            if ($segment === '..') {
                if ($segments === []) {
                    throw new AuthApiException(
                        400,
                        'INVALID_PLAYBACK_ASSET_PATH',
                        'The playback asset path escapes the protected video root.'
                    );
                }

                array_pop(
                    $segments
                );

                continue;
            }

            $segments[] =
                $segment;
        }

        if ($segments === []) {
            throw new AuthApiException(
                400,
                'INVALID_PLAYBACK_ASSET_PATH',
                'The playback asset path is invalid.'
            );
        }

        return implode(
            '/',
            $segments
        );
    }

    private function gatewayUrl(
        string $plainToken,
        string $relativePath
    ): string {
        $encodedPath =
            collect(
                explode(
                    '/',
                    $relativePath
                )
            )
                ->map(
                    fn (
                        string $part
                    ): string =>
                        rawurlencode(
                            $part
                        )
                )
                ->implode('/');

        return url(
            '/api/v1/education/playback/'
            .$plainToken
            .'/'
            .$encodedPath
        );
    }

    private function contentType(
        string $path
    ): string {
        return match (
            strtolower(
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            )
        ) {
            'ts' =>
                'video/mp2t',

            'm4s' =>
                'video/iso.segment',

            'mp4' =>
                'video/mp4',

            'aac' =>
                'audio/aac',

            'key' =>
                'application/octet-stream',

            default =>
                'application/octet-stream',
        };
    }
}
