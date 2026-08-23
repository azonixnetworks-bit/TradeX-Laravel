<?php

namespace Tests\Feature\Api\OurChannel;

use App\Models\ChannelVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOurChannelApiContent;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class OurChannelApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;
    use CreatesOurChannelApiContent;

    public function test_free_user_receives_only_currently_published_videos(): void
    {
        $published = $this->createOurChannelVideo();

        $this->createOurChannelVideo(
            ChannelVideo::STATUS_DRAFT
        );

        $this->createOurChannelVideo(
            ChannelVideo::STATUS_ARCHIVED,
            [
                'published_at' =>
                    now()->subDay(),
            ]
        );

        $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'published_at' =>
                    now()->addHour(),
            ]
        );

        $user = $this->createApiUser([
            'account_type' => 'FREE',
        ]);

        $response = $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.videos.0.uuid',
                $published->uuid
            );

        $this->assertSame(
            'YOUTUBE',
            $response->json(
                'data.videos.0.youtube.provider'
            )
        );
    }

    public function test_premium_user_can_access_the_same_free_channel_catalog(): void
    {
        $video = $this->createOurChannelVideo();

        $user = $this->createApiUser([
            'account_type' => 'PREMIUM',
        ]);

        $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.videos.0.uuid',
                $video->uuid
            );
    }

    public function test_language_and_featured_filters_are_applied(): void
    {
        $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'language_code' => 'en',
                'is_featured' => false,
            ]
        );

        $featuredTamil = $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'language_code' => 'ta',
                'is_featured' => true,
                'sort_order' => 1,
            ]
        );

        $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'language_code' => 'ta',
                'is_featured' => false,
                'sort_order' => 2,
            ]
        );

        $user = $this->createApiUser();

        $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos?language_code=TA&featured=1'
            )
            ->assertOk()
            ->assertJsonPath(
                'meta.total',
                1
            )
            ->assertJsonPath(
                'data.videos.0.uuid',
                $featuredTamil->uuid
            )
            ->assertJsonPath(
                'data.filters.language_code',
                'ta'
            )
            ->assertJsonPath(
                'data.filters.featured',
                true
            );
    }

    public function test_detail_uses_uuid_and_returns_privacy_enhanced_youtube_metadata(): void
    {
        $video = $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'youtube_video_id' =>
                    'dQw4w9WgXcQ',
            ]
        );

        $user = $this->createApiUser();

        $response = $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos/'
                .$video->uuid
            )
            ->assertOk()
            ->assertJsonPath(
                'data.video.uuid',
                $video->uuid
            )
            ->assertJsonPath(
                'data.video.youtube.video_id',
                'dQw4w9WgXcQ'
            )
            ->assertJsonPath(
                'data.video.youtube.watch_url',
                'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
            )
            ->assertJsonPath(
                'data.video.youtube.embed_url',
                'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'
            )
            ->assertJsonPath(
                'data.video.youtube.thumbnail_url',
                'https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg'
            )
            ->assertJsonPath(
                'data.video.youtube.privacy_enhanced_embed',
                true
            );

        $payload = $response->json(
            'data.video'
        );

        $this->assertArrayNotHasKey(
            'id',
            $payload
        );
        $this->assertArrayNotHasKey(
            'sort_order',
            $payload
        );
        $this->assertArrayNotHasKey(
            'created_by_admin_id',
            $payload
        );
        $this->assertArrayNotHasKey(
            'updated_by_admin_id',
            $payload
        );
    }

    public function test_unpublished_uuid_is_hidden_from_mobile_api(): void
    {
        $draft = $this->createOurChannelVideo(
            ChannelVideo::STATUS_DRAFT
        );

        $user = $this->createApiUser();

        $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos/'
                .$draft->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'OUR_CHANNEL_VIDEO_NOT_AVAILABLE'
            );
    }

    public function test_future_published_uuid_is_hidden_until_publish_time(): void
    {
        $future = $this->createOurChannelVideo(
            ChannelVideo::STATUS_PUBLISHED,
            [
                'published_at' =>
                    now()->addHour(),
            ]
        );

        $user = $this->createApiUser();

        $this
            ->withToken(
                $this->issueBoundToken($user)
            )
            ->getJson(
                '/api/v1/our-channel/videos/'
                .$future->uuid
            )
            ->assertNotFound()
            ->assertJsonPath(
                'error.code',
                'OUR_CHANNEL_VIDEO_NOT_AVAILABLE'
            );
    }
}
