<?php

namespace App\Services\Education;

use App\Models\LessonVideo;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProtectedHlsMediaService
{
    /**
     * Return the master manifest path relative to the protected HLS root.
     */
    public function masterRelativePath(
        LessonVideo $video
    ): string {
        $masterPath = $this->validatedLogicalPath(
            (string) $video->hls_master_path
        );

        $root = $this->hlsRoot(
            $masterPath
        );

        $prefix = $root === ''
            ? ''
            : $root.'/';

        if (! str_starts_with($masterPath, $prefix)) {
            throw new RuntimeException(
                'The protected HLS master path is outside its calculated media root.'
            );
        }

        $relative = substr(
            $masterPath,
            strlen($prefix)
        );

        if ($relative === false || $relative === '') {
            throw new RuntimeException(
                'The protected HLS master manifest path is invalid.'
            );
        }

        return $relative;
    }

    /**
     * Verify that the configured protected master manifest exists.
     */
    public function masterExists(
        LessonVideo $video
    ): bool {
        if (! $video->hasHlsStream()) {
            return false;
        }

        try {
            $diskName = $this->approvedDisk(
                (string) $video->storage_disk
            );

            $masterPath = $this->validatedLogicalPath(
                (string) $video->hls_master_path
            );

            return Storage::disk(
                $diskName
            )->exists(
                $masterPath
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return a protected manifest/segment response.
     *
     * $urlBuilder receives an HLS-root-relative asset path and must return
     * the protected application URL for that asset.
     */
    public function responseFor(
        LessonVideo $video,
        string $requestedPath,
        Closure $urlBuilder
    ): Response {
        $diskName = $this->approvedDisk(
            (string) $video->storage_disk
        );

        $masterPath = $this->validatedLogicalPath(
            (string) $video->hls_master_path
        );

        $root = $this->hlsRoot(
            $masterPath
        );

        $relativePath = $this->validatedRelativeAssetPath(
            $requestedPath
        );

        $absolutePath = $root === ''
            ? $relativePath
            : $root.'/'.$relativePath;

        $absolutePath = $this->validatedLogicalPath(
            $absolutePath
        );

        if (
            $root !== ''
            && ! str_starts_with(
                $absolutePath,
                $root.'/'
            )
        ) {
            throw new NotFoundHttpException();
        }

        $extension = strtolower(
            (string) pathinfo(
                $absolutePath,
                PATHINFO_EXTENSION
            )
        );

        if (! in_array(
            $extension,
            [
                'm3u8',
                'ts',
                'm4s',
                'mp4',
                'aac',
                'vtt',
            ],
            true
        )) {
            throw new NotFoundHttpException();
        }

        $disk = Storage::disk(
            $diskName
        );

        if (! $disk->exists($absolutePath)) {
            throw new NotFoundHttpException();
        }

        if ($extension === 'm3u8') {
            $manifest = $disk->get(
                $absolutePath
            );

            $manifest = $this->rewriteManifest(
                $manifest,
                $relativePath,
                $urlBuilder
            );

            return response(
                $manifest,
                200,
                $this->securityHeaders(
                    'application/vnd.apple.mpegurl; charset=UTF-8'
                )
            );
        }

        $content = $disk->get(
            $absolutePath
        );

        return response(
            $content,
            200,
            array_merge(
                $this->securityHeaders(
                    $this->contentTypeFor(
                        $extension
                    )
                ),
                [
                    'Content-Length' => (string) strlen(
                        $content
                    ),
                ]
            )
        );
    }

    /**
     * Rewrite all non-comment HLS URI lines and quoted URI="..." attributes
     * so the browser never receives direct private-storage paths.
     */
    private function rewriteManifest(
        string $manifest,
        string $manifestRelativePath,
        Closure $urlBuilder
    ): string {
        $lines = preg_split(
            '/\r\n|\r|\n/',
            $manifest
        );

        if (! is_array($lines)) {
            throw new RuntimeException(
                'Unable to parse protected HLS manifest.'
            );
        }

        $rewritten = [];

        foreach ($lines as $line) {
            $trimmed = trim(
                $line
            );

            if ($trimmed === '') {
                $rewritten[] = $line;

                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                $rewritten[] = preg_replace_callback(
                    '/URI="([^"]+)"/i',
                    function (array $matches) use (
                        $manifestRelativePath,
                        $urlBuilder
                    ): string {
                        $resolved = $this->resolveManifestUri(
                            $manifestRelativePath,
                            $matches[1]
                        );

                        return 'URI="'
                            .$urlBuilder($resolved)
                            .'"';
                    },
                    $line
                ) ?? $line;

                continue;
            }

            $resolved = $this->resolveManifestUri(
                $manifestRelativePath,
                $trimmed
            );

            $rewritten[] = $urlBuilder(
                $resolved
            );
        }

        return implode(
            "\n",
            $rewritten
        );
    }

    private function resolveManifestUri(
        string $manifestRelativePath,
        string $uri
    ): string {
        $uri = trim(
            html_entity_decode(
                $uri,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            )
        );

        if (
            $uri === ''
            || str_starts_with($uri, '/')
            || str_starts_with($uri, '//')
            || preg_match(
                '/^[A-Za-z][A-Za-z0-9+.-]*:/',
                $uri
            ) === 1
            || str_contains($uri, '?')
            || str_contains($uri, '#')
        ) {
            throw new NotFoundHttpException();
        }

        $manifestDirectory = str_replace(
            '\\',
            '/',
            dirname(
                $manifestRelativePath
            )
        );

        if ($manifestDirectory === '.') {
            $manifestDirectory = '';
        }

        $candidate = $manifestDirectory === ''
            ? $uri
            : $manifestDirectory.'/'.$uri;

        return $this->validatedRelativeAssetPath(
            $candidate
        );
    }

    private function approvedDisk(
        string $diskName
    ): string {
        $diskName = trim(
            $diskName
        );

        if (! in_array(
            $diskName,
            [
                'private',
                's3-private',
            ],
            true
        )) {
            throw new NotFoundHttpException();
        }

        return $diskName;
    }

    private function hlsRoot(
        string $masterPath
    ): string {
        $root = str_replace(
            '\\',
            '/',
            dirname(
                $masterPath
            )
        );

        return $root === '.'
            ? ''
            : trim(
                $root,
                '/'
            );
    }

    private function validatedRelativeAssetPath(
        string $path
    ): string {
        $decoded = $this->decodePath(
            $path
        );

        $normalized = str_replace(
            '\\',
            '/',
            trim(
                $decoded
            )
        );

        $hasTraversal = preg_match(
            '~(?:^|/)\.\.(?:/|$)~',
            $normalized
        ) === 1;

        $hasDotSegment = preg_match(
            '~(?:^|/)\.(?:/|$)~',
            $normalized
        ) === 1;

        $hasScheme = preg_match(
            '/^[A-Za-z][A-Za-z0-9+.-]*:/',
            $normalized
        ) === 1;

        $hasControlCharacters = preg_match(
            '/[\x00-\x1F\x7F]/',
            $normalized
        ) === 1;

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || $hasTraversal
            || $hasDotSegment
            || $hasScheme
            || str_contains($normalized, '?')
            || str_contains($normalized, '#')
            || $hasControlCharacters
        ) {
            throw new NotFoundHttpException();
        }

        $segments = explode(
            '/',
            $normalized
        );

        foreach ($segments as $segment) {
            if (
                $segment === ''
                || $segment === '.'
                || $segment === '..'
            ) {
                throw new NotFoundHttpException();
            }
        }

        return implode(
            '/',
            $segments
        );
    }

    private function validatedLogicalPath(
        string $path
    ): string {
        $normalized = str_replace(
            '\\',
            '/',
            trim(
                $path
            )
        );

        $hasTraversal = preg_match(
            '~(?:^|/)\.\.(?:/|$)~',
            $normalized
        ) === 1;

        $hasScheme = preg_match(
            '/^[A-Za-z][A-Za-z0-9+.-]*:/',
            $normalized
        ) === 1;

        $hasControlCharacters = preg_match(
            '/[\x00-\x1F\x7F]/',
            $normalized
        ) === 1;

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || $hasTraversal
            || $hasScheme
            || str_contains($normalized, '?')
            || str_contains($normalized, '#')
            || $hasControlCharacters
        ) {
            throw new NotFoundHttpException();
        }

        return trim(
            $normalized,
            '/'
        );
    }

    private function decodePath(
        string $path
    ): string {
        $current = $path;

        for ($iteration = 0; $iteration < 3; $iteration++) {
            $decoded = rawurldecode(
                $current
            );

            if ($decoded === $current) {
                break;
            }

            $current = $decoded;
        }

        return $current;
    }

    private function contentTypeFor(
        string $extension
    ): string {
        return match ($extension) {
            'ts' => 'video/mp2t',
            'm4s' => 'video/iso.segment',
            'mp4' => 'video/mp4',
            'aac' => 'audio/aac',
            'vtt' => 'text/vtt; charset=UTF-8',
            default => 'application/octet-stream',
        };
    }

    private function securityHeaders(
        string $contentType
    ): array {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Referrer-Policy' => 'no-referrer',
        ];
    }
}
