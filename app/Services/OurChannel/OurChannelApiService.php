<?php

namespace App\Services\OurChannel;

use App\Exceptions\AuthApiException;
use App\Models\ChannelVideo;
use App\Models\User;
use App\Models\UserChannelVideoReaction;
use App\Services\Dashboard\DashboardApiService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

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
        ChannelVideo $video,
        ?User $viewer = null
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
            'reactions' => $this->reactionSummary($video, $viewer),
        ];
    }

    public function detail(
        ChannelVideo $video,
        ?User $viewer = null
    ): array {
        return [
            ...$this->summary($video, $viewer),
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

    public function react(
        User $user,
        ChannelVideo $video,
        ?string $reaction
    ): array {
        if ($reaction === null) {
            UserChannelVideoReaction::query()
                ->where('user_id', $user->getKey())
                ->where('channel_video_id', $video->getKey())
                ->delete();
        } else {
            UserChannelVideoReaction::query()->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'channel_video_id' => $video->getKey(),
                ],
                ['reaction' => $reaction]
            );
        }

        Cache::forget(DashboardApiService::cacheKey($user));

        return $this->reactionSummary($video, $user);
    }

    private function reactionSummary(
        ChannelVideo $video,
        ?User $viewer
    ): array {
        $counts = UserChannelVideoReaction::query()
            ->select('reaction')
            ->selectRaw('COUNT(*) as aggregate')
            ->where('channel_video_id', $video->getKey())
            ->groupBy('reaction')
            ->pluck('aggregate', 'reaction');

        $viewerReaction = $viewer instanceof User
            ? UserChannelVideoReaction::query()
                ->where('user_id', $viewer->getKey())
                ->where('channel_video_id', $video->getKey())
                ->value('reaction')
            : null;

        return [
            'likes_count' => (int) $counts->get(UserChannelVideoReaction::LIKE, 0),
            'dislikes_count' => (int) $counts->get(UserChannelVideoReaction::DISLIKE, 0),
            'viewer_reaction' => $viewerReaction,
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
