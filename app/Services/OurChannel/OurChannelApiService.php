<?php

namespace App\Services\OurChannel;

use App\Exceptions\AuthApiException;
use App\Models\ChannelVideo;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OurChannelApiService
{
    public function index(
        User $user,
        array $filters
    ): LengthAwarePaginator {
        $this->assertUserMayRead(
            $user
        );

        $query = ChannelVideo::query()
            ->published();

        if (
            isset($filters['language_code'])
            && $filters['language_code'] !== null
            && $filters['language_code'] !== ''
        ) {
            $query->where(
                'language_code',
                $filters['language_code']
            );
        }

        if (
            array_key_exists(
                'featured',
                $filters
            )
            && $filters['featured'] !== null
        ) {
            $query->where(
                'is_featured',
                (bool) $filters['featured']
            );
        }

        $perPage = min(
            max(
                1,
                (int) ($filters['per_page']
                    ?? config(
                        'tradex_our_channel_api.default_per_page',
                        20
                    ))
            ),
            max(
                1,
                (int) config(
                    'tradex_our_channel_api.max_per_page',
                    50
                )
            )
        );

        return $query
            ->ordered()
            ->paginate(
                $perPage
            )
            ->withQueryString();
    }

    public function resolve(
        User $user,
        string $uuid
    ): ChannelVideo {
        $this->assertUserMayRead(
            $user
        );

        $video = ChannelVideo::query()
            ->published()
            ->where(
                'uuid',
                $uuid
            )
            ->first();

        if (! $video instanceof ChannelVideo) {
            throw new AuthApiException(
                404,
                'OUR_CHANNEL_VIDEO_NOT_AVAILABLE',
                'Our Channel video is not available.'
            );
        }

        return $video;
    }

    public function summary(
        ChannelVideo $video
    ): array {
        return [
            'uuid' => $video->uuid,
            'title' => $video->title,
            'short_description' =>
                $video->short_description,
            'language_code' =>
                $video->language_code,
            'is_featured' =>
                (bool) $video->is_featured,
            'published_at' =>
                $video->published_at?->toISOString(),
            'youtube' =>
                $this->youtubePayload(
                    $video
                ),
        ];
    }

    public function detail(
        ChannelVideo $video
    ): array {
        return [
            ...$this->summary(
                $video
            ),
            'description' =>
                $video->description,
        ];
    }

    private function youtubePayload(
        ChannelVideo $video
    ): array {
        return [
            'provider' => 'YOUTUBE',
            'video_id' =>
                $video->youtube_video_id,
            'watch_url' =>
                $video->canonicalYouTubeUrl(),
            'embed_url' =>
                $video->embedUrl(),
            'thumbnail_url' =>
                $video->thumbnailUrl(),
            'privacy_enhanced_embed' => true,
        ];
    }

    private function assertUserMayRead(
        User $user
    ): void {
        if (
            ! $user->isActive()
            || $user->trashed()
        ) {
            throw new AuthApiException(
                403,
                'OUR_CHANNEL_ACCESS_DENIED',
                'Our Channel access is not available for this account.'
            );
        }

        /*
        | Our Channel is intentionally available to both FREE and PREMIUM
        | active users. Premium entitlement must never be added here.
        */
    }
}
