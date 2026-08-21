<?php

namespace App\Support;

final class TradingViewUrl
{
    public static function normalize(
        ?string $url
    ): ?string {
        if ($url === null) {
            return null;
        }

        $url = trim($url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

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
            || $host === ''
            || isset($parts['user'])
            || isset($parts['pass'])
            || (
                isset($parts['port'])
                && (int) $parts['port'] !== 443
            )
        ) {
            return null;
        }

        $isTradingViewHost = $host === 'tradingview.com'
            || str_ends_with(
                $host,
                '.tradingview.com'
            );

        if (! $isTradingViewHost) {
            return null;
        }

        $path = '/'.ltrim(
            (string) ($parts['path'] ?? ''),
            '/'
        );

        if (
            ! str_starts_with(
                $path,
                '/chart/'
            )
            && ! str_starts_with(
                $path,
                '/x/'
            )
        ) {
            return null;
        }

        $normalized = 'https://'.$host.$path;

        $query = trim(
            (string) ($parts['query'] ?? '')
        );

        if ($query !== '') {
            $normalized .= '?'.$query;
        }

        return $normalized;
    }
}
