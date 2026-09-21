<?php

namespace App\Services\OurChannel;

use App\Jobs\PublishChannelVideoToPlatform;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;

class ChannelPublicationService
{
    public function __construct(
        private readonly ChannelConnectionResolver $connections
    ) {
    }

    public function createTargets(
        ChannelVideo $video,
        array $platforms
    ): void {
        $platforms = array_values(
            array_intersect(
                array_unique($platforms),
                ChannelVideoPublication::allPlatforms()
            )
        );

        foreach ($platforms as $platform) {
            $publication = ChannelVideoPublication::query()
                ->firstOrNew([
                    'channel_video_id' => $video->id,
                    'platform' => $platform,
                ]);

            if (
                in_array(
                    $platform,
                    ChannelVideoPublication::actionRequiredPlatforms(),
                    true
                )
            ) {
                $publication->status =
                    ChannelVideoPublication::STATUS_ACTION_REQUIRED;

                $publication->status_message =
                    $this->actionRequiredMessage($platform);
            } elseif (! $this->providerEnabled($platform)) {
                $publication->status =
                    ChannelVideoPublication::STATUS_ACTION_REQUIRED;

                $publication->status_message =
                    $publication->platformLabel()
                    .' credentials are not enabled yet.';
            } else {
                $publication->status =
                    ChannelVideoPublication::STATUS_PENDING;

                $publication->status_message = null;
            }

            $publication->save();
        }
    }

    public function queueForVideo(ChannelVideo $video): void
    {
        $video->loadMissing('publications');

        foreach ($video->publications as $publication) {
            if ($publication->isPublished()) {
                continue;
            }

            $this->queuePublication($publication);
        }

        $video->forceFill([
            'publish_requested_at' => now(),
        ])->save();
    }

    public function retry(
        ChannelVideoPublication $publication
    ): void {
        $this->queuePublication($publication);
    }

    private function queuePublication(
        ChannelVideoPublication $publication
    ): void {
        if (
            in_array(
                $publication->platform,
                ChannelVideoPublication::actionRequiredPlatforms(),
                true
            )
        ) {
            $publication->forceFill([
                'status' =>
                    ChannelVideoPublication::STATUS_ACTION_REQUIRED,
                'status_message' =>
                    $this->actionRequiredMessage(
                        $publication->platform
                    ),
                'failed_at' => null,
            ])->save();

            return;
        }

        if (! $this->providerEnabled($publication->platform)) {
            $publication->forceFill([
                'status' =>
                    ChannelVideoPublication::STATUS_ACTION_REQUIRED,
                'status_message' =>
                    $publication->platformLabel()
                    .' credentials are not enabled yet.',
                'failed_at' => null,
            ])->save();

            return;
        }

        $publication->loadMissing('channelVideo');
        $video = $publication->channelVideo;

        if (
            $video === null
            || ! $video->hasSourceMedia()
        ) {
            $publication->forceFill([
                'status' =>
                    ChannelVideoPublication::STATUS_FAILED,
                'status_message' =>
                    'A protected master video upload is required for cross-platform publishing.',
                'failed_at' => now(),
            ])->save();

            return;
        }

        $publication->forceFill([
            'status' =>
                ChannelVideoPublication::STATUS_PENDING,
            'status_message' => null,
            'queued_at' => now(),
            'started_at' => null,
            'failed_at' => null,
        ])->save();

        PublishChannelVideoToPlatform::dispatch(
            $publication->id
        )
            ->onQueue(
                (string) config(
                    'tradex_channels.queue',
                    'channel-publishing'
                )
            )
            ->afterCommit();
    }

    private function providerEnabled(string $platform): bool
    {
        return $this->connections->isEnabled(
            $platform
        );
    }

    private function actionRequiredMessage(
        string $platform
    ): string {
        return match ($platform) {
            ChannelVideoPublication::PLATFORM_TIKTOK =>
                'TikTok requires creator info, manual privacy selection and explicit consent before each Direct Post.',

            ChannelVideoPublication::PLATFORM_WHATSAPP_CHANNEL =>
                'WhatsApp Channel posting remains manual until an official supported Channel publishing API is available.',

            default =>
                'Manual action is required before publishing.',
        };
    }
}
