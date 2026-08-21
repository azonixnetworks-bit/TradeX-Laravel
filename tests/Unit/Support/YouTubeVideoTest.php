<?php

namespace Tests\Unit\Support;

use App\Support\YouTubeVideo;
use PHPUnit\Framework\TestCase;

class YouTubeVideoTest extends TestCase
{
    public function test_supported_youtube_urls_are_normalized_to_video_id(): void
    {
        $id = 'dQw4w9WgXcQ';

        $urls = [
            'https://www.youtube.com/watch?v='.$id,
            'https://youtube.com/watch?v='.$id.'&feature=share',
            'https://youtu.be/'.$id,
            'https://www.youtube.com/shorts/'.$id,
            'https://www.youtube.com/embed/'.$id,
            'https://www.youtube.com/live/'.$id,
            'https://www.youtube-nocookie.com/embed/'.$id,
        ];

        foreach ($urls as $url) {
            $this->assertSame(
                $id,
                YouTubeVideo::extractVideoId(
                    $url
                ),
                $url
            );
        }
    }

    public function test_untrusted_or_non_https_hosts_are_rejected(): void
    {
        $id = 'dQw4w9WgXcQ';

        $urls = [
            'http://www.youtube.com/watch?v='.$id,
            'https://evil.example/watch?v='.$id,
            'https://youtube.com.evil.example/watch?v='.$id,
            'https://evil.youtube.com/watch?v='.$id,
            'javascript:alert(1)',
            'https://www.youtube.com/watch?v=too-short',
        ];

        foreach ($urls as $url) {
            $this->assertNull(
                YouTubeVideo::extractVideoId(
                    $url
                ),
                $url
            );
        }
    }

    public function test_canonical_embed_and_thumbnail_urls_use_only_valid_video_id(): void
    {
        $id = 'dQw4w9WgXcQ';

        $this->assertSame(
            'https://www.youtube.com/watch?v='.$id,
            YouTubeVideo::canonicalUrl(
                $id
            )
        );

        $this->assertSame(
            'https://www.youtube-nocookie.com/embed/'.$id,
            YouTubeVideo::embedUrl(
                $id
            )
        );

        $this->assertSame(
            'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg',
            YouTubeVideo::thumbnailUrl(
                $id
            )
        );
    }
}
