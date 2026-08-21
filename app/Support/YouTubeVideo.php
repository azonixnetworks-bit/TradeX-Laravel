<?php

namespace App\Support;

final class YouTubeVideo
{
    private const VIDEO_ID_PATTERN = '/^[A-Za-z0-9_-]{11}$/';

    private const ALLOWED_HOSTS = [
        'youtube.com',
        'www.youtube.com',
        'm.youtube.com',
        'youtu.be',
        'www.youtu.be',
        'youtube-nocookie.com',
        'www.youtube-nocookie.com',
    ];

    public static function extractVideoId(
        string $url
    ): ?string {
        $url = trim(
            $url
        );

        if ($url === '') {
            return null;
        }

        $parts = parse_url(
            $url
        );

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower(
            (string) ($parts['scheme'] ?? '')
        );

        $host = strtolower(
            rtrim(
                (string) ($parts['host'] ?? ''),
                '.'
            )
        );

        if (
            $scheme !== 'https'
            || ! in_array($host, self::ALLOWED_HOSTS, true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            return null;
        }

        $path = trim(
            (string) ($parts['path'] ?? ''),
            '/'
        );

        if (
            $host === 'youtu.be'
            || $host === 'www.youtu.be'
        ) {
            $candidate = explode(
                '/',
                $path
            )[0] ?? '';

            return self::validatedId(
                $candidate
            );
        }

        if ($path === 'watch') {
            parse_str(
                (string) ($parts['query'] ?? ''),
                $query
            );

            return self::validatedId(
                (string) ($query['v'] ?? '')
            );
        }

        $segments = array_values(
            array_filter(
                explode(
                    '/',
                    $path
                ),
                static fn (string $segment): bool => $segment !== ''
            )
        );

        if (
            count($segments) >= 2
            && in_array(
                strtolower($segments[0]),
                [
                    'shorts',
                    'embed',
                    'live',
                ],
                true
            )
        ) {
            return self::validatedId(
                $segments[1]
            );
        }

        return null;
    }

    public static function canonicalUrl(
        string $videoId
    ): string {
        $videoId = self::requireValidId(
            $videoId
        );

        return 'https://www.youtube.com/watch?v='.$videoId;
    }

    public static function embedUrl(
        string $videoId
    ): string {
        $videoId = self::requireValidId(
            $videoId
        );

        return 'https://www.youtube-nocookie.com/embed/'.$videoId;
    }

    public static function thumbnailUrl(
        string $videoId
    ): string {
        $videoId = self::requireValidId(
            $videoId
        );

        return 'https://i.ytimg.com/vi/'.$videoId.'/hqdefault.jpg';
    }

    private static function validatedId(
        string $candidate
    ): ?string {
        $candidate = trim(
            rawurldecode(
                $candidate
            )
        );

        return preg_match(
            self::VIDEO_ID_PATTERN,
            $candidate
        ) === 1
            ? $candidate
            : null;
    }

    private static function requireValidId(
        string $videoId
    ): string {
        $videoId = trim(
            $videoId
        );

        if (
            preg_match(
                self::VIDEO_ID_PATTERN,
                $videoId
            ) !== 1
        ) {
            throw new \InvalidArgumentException(
                'Invalid YouTube video ID.'
            );
        }

        return $videoId;
    }
}
