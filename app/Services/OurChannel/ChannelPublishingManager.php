<?php

namespace App\Services\OurChannel;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideoPublication;

class ChannelPublishingManager
{
    public function __construct(
        private readonly YouTubeChannelPublisher $youtube,
        private readonly InstagramChannelPublisher $instagram,
        private readonly FacebookChannelPublisher $facebook,
        private readonly TelegramChannelPublisher $telegram
    ) {
    }

    public function publish(
        ChannelVideoPublication $publication
    ): array {
        $publication->loadMissing('channelVideo');

        $video = $publication->channelVideo;

        if ($video === null) {
            throw new ChannelPublicationException(
                $publication->platform,
                'VIDEO_MISSING',
                'The TradeX channel video no longer exists.'
            );
        }

        return match ($publication->platform) {
            ChannelVideoPublication::PLATFORM_YOUTUBE =>
                $this->youtube->publish(
                    $video,
                    $publication
                ),

            ChannelVideoPublication::PLATFORM_INSTAGRAM =>
                $this->instagram->publish(
                    $video,
                    $publication
                ),

            ChannelVideoPublication::PLATFORM_FACEBOOK =>
                $this->facebook->publish(
                    $video,
                    $publication
                ),

            ChannelVideoPublication::PLATFORM_TELEGRAM =>
                $this->telegram->publish(
                    $video,
                    $publication
                ),

            ChannelVideoPublication::PLATFORM_TIKTOK =>
                throw new ChannelPublicationException(
                    $publication->platform,
                    'CREATOR_CONSENT_REQUIRED',
                    'TikTok requires an interactive creator confirmation flow for each post.'
                ),

            ChannelVideoPublication::PLATFORM_WHATSAPP_CHANNEL =>
                throw new ChannelPublicationException(
                    $publication->platform,
                    'CHANNEL_API_UNAVAILABLE',
                    'WhatsApp Channel publishing is awaiting an official supported API.'
                ),

            default =>
                throw new ChannelPublicationException(
                    $publication->platform,
                    'PLATFORM_UNSUPPORTED',
                    'This channel publishing platform is not supported.'
                ),
        };
    }
}
