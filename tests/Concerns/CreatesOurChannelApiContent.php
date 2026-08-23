<?php

namespace Tests\Concerns;

use App\Models\ChannelVideo;
use App\Support\YouTubeVideo;
use Illuminate\Support\Str;

trait CreatesOurChannelApiContent
{
    protected function createOurChannelVideo(
        string $status = ChannelVideo::STATUS_PUBLISHED,
        array $overrides = []
    ): ChannelVideo {
        $videoId = $overrides['youtube_video_id']
            ?? Str::random(11);

        $publishedAt = array_key_exists(
            'published_at',
            $overrides
        )
            ? $overrides['published_at']
            : (
                $status === ChannelVideo::STATUS_PUBLISHED
                    ? now()->subMinute()
                    : null
            );

        return ChannelVideo::query()->create(
            array_merge(
                [
                    'title' =>
                        'TradeX Market Education Video',
                    'short_description' =>
                        'Professional trading education from Our Channel.',
                    'description' =>
                        'A free educational YouTube video available inside TradeX OS.',
                    'youtube_video_id' =>
                        $videoId,
                    'youtube_url' =>
                        YouTubeVideo::canonicalUrl(
                            $videoId
                        ),
                    'language_code' =>
                        'en',
                    'status' =>
                        $status,
                    'published_at' =>
                        $publishedAt,
                    'is_featured' =>
                        false,
                    'sort_order' =>
                        10,
                ],
                $overrides
            )
        );
    }
}
