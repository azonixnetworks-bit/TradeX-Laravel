<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ChannelMediaSource
{
    public function exists(ChannelVideo $video): bool
    {
        try {
            [$disk, $path] = $this->location($video);

            return Storage::disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public function open(ChannelVideo $video)
    {
        [$disk, $path] = $this->location($video);

        $stream = Storage::disk($disk)->readStream($path);

        if (! is_resource($stream)) {
            throw new ChannelPublicationException(
                'MEDIA',
                'SOURCE_READ_FAILED',
                'The protected master video could not be read.'
            );
        }

        return $stream;
    }

    public function size(ChannelVideo $video): int
    {
        if (
            $video->source_size_bytes !== null
            && (int) $video->source_size_bytes > 0
        ) {
            return (int) $video->source_size_bytes;
        }

        [$disk, $path] = $this->location($video);

        try {
            $size = Storage::disk($disk)->size($path);
        } catch (Throwable) {
            $size = false;
        }

        if (! is_int($size) || $size <= 0) {
            throw new ChannelPublicationException(
                'MEDIA',
                'SOURCE_SIZE_UNKNOWN',
                'The master video size could not be determined.'
            );
        }

        return $size;
    }

    public function mime(ChannelVideo $video): string
    {
        $stored = trim((string) $video->source_mime_type);

        if ($stored !== '') {
            return $stored;
        }

        [$disk, $path] = $this->location($video);

        try {
            $mime = Storage::disk($disk)->mimeType($path);
        } catch (Throwable) {
            $mime = null;
        }

        if (! is_string($mime) || trim($mime) === '') {
            return 'video/mp4';
        }

        return trim($mime);
    }

    public function filename(ChannelVideo $video): string
    {
        $name = basename(
            trim((string) $video->source_original_name)
        );

        if ($name === '' || $name === '.' || $name === '..') {
            $name = basename(
                trim((string) $video->source_path)
            );
        }

        $safe = preg_replace(
            '/[^A-Za-z0-9._-]+/',
            '_',
            $name
        );

        return is_string($safe) && $safe !== ''
            ? Str::limit($safe, 180, '')
            : 'tradex-video.mp4';
    }

    public function signedUrl(ChannelVideo $video): string
    {
        $minutes = max(
            15,
            (int) config(
                'tradex_channels.signed_media_minutes',
                180
            )
        );

        return URL::temporarySignedRoute(
            'channel-media.source',
            now()->addMinutes($minutes),
            [
                'channelVideo' => $video->getKey(),
            ]
        );
    }

    private function location(ChannelVideo $video): array
    {
        $disk = trim((string) $video->source_disk);
        $path = str_replace(
            '\\',
            '/',
            trim((string) $video->source_path)
        );

        if (
            ! in_array(
                $disk,
                [
                    'private',
                    's3-private',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Channel media refused an unapproved storage disk.'
            );
        }

        $unsafe = $path === ''
            || str_starts_with($path, '/')
            || preg_match(
                '~(?:^|/)\.\.(?:/|$)~',
                $path
            ) === 1
            || preg_match(
                '/^[A-Za-z][A-Za-z0-9+.-]*:/',
                $path
            ) === 1
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $path
            ) === 1;

        if ($unsafe) {
            throw new RuntimeException(
                'Channel media refused an unsafe source path.'
            );
        }

        return [
            $disk,
            $path,
        ];
    }
}
