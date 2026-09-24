<?php

namespace App\Jobs;

use App\Exceptions\ChannelPublicationException;
use App\Models\ChannelVideoPublication;
use App\Services\OurChannel\ChannelPublishingManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishChannelVideoToPlatform implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries;

    public int $timeout = 2100;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 2400;

    public function __construct(
        public readonly int $publicationId
    ) {
        $this->tries = max(
            1,
            (int) config(
                'tradex_channels.jobs.tries',
                3
            )
        );
    }

    public function uniqueId(): string
    {
        return 'channel-publication:'.$this->publicationId;
    }

    public function backoff(): array
    {
        return (array) config(
            'tradex_channels.jobs.backoff_seconds',
            [
                30,
                120,
                300,
            ]
        );
    }

    public function handle(
        ChannelPublishingManager $manager
    ): void {
        $publication = ChannelVideoPublication::query()
            ->with('channelVideo')
            ->find($this->publicationId);

        if (
            ! $publication instanceof ChannelVideoPublication
            || $publication->isPublished()
        ) {
            return;
        }

        if (
            ! in_array(
                $publication->platform,
                ChannelVideoPublication::automaticPlatforms(),
                true
            )
        ) {
            return;
        }

        $publication->forceFill([
            'status' =>
                ChannelVideoPublication::STATUS_PROCESSING,
            'attempt_count' =>
                $publication->attempt_count + 1,
            'started_at' => now(),
            'failed_at' => null,
            'status_message' => null,
        ])->save();

        try {
            $result = $manager->publish($publication);

            $publication->forceFill([
                'status' =>
                    ChannelVideoPublication::STATUS_PUBLISHED,
                'provider_post_id' =>
                    $result['provider_post_id']
                    ?? null,
                'provider_url' =>
                    $result['provider_url']
                    ?? null,
                'response_meta' =>
                    $result['response_meta']
                    ?? null,
                'published_at' => now(),
                'failed_at' => null,
                'status_message' => null,
            ])->save();

            $this->syncYouTubeCatalogue(
                $publication,
                $result
            );
        } catch (ChannelPublicationException $exception) {
            $this->markFailed(
                $publication,
                $exception->providerCode,
                $exception->getMessage()
            );

            throw $exception;
        } catch (Throwable) {
            $safe = new ChannelPublicationException(
                $publication->platform,
                'UNEXPECTED',
                'Unexpected channel publishing failure.'
            );

            $this->markFailed(
                $publication,
                $safe->providerCode,
                $safe->getMessage()
            );

            throw $safe;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $publication = ChannelVideoPublication::query()
            ->find($this->publicationId);

        if (
            ! $publication instanceof ChannelVideoPublication
            || $publication->isPublished()
        ) {
            return;
        }

        $publication->forceFill([
            'status' =>
                ChannelVideoPublication::STATUS_FAILED,
            'failed_at' => now(),
            'status_message' =>
                $publication->status_message
                ?? 'Channel publishing job failed after all retries.',
        ])->save();
    }

    private function markFailed(
        ChannelVideoPublication $publication,
        string $code,
        string $message
    ): void {
        $finalAttempt = $this->attempts() >= $this->tries;

        $publication->forceFill([
            'status' => $finalAttempt
                ? ChannelVideoPublication::STATUS_FAILED
                : ChannelVideoPublication::STATUS_PENDING,
            'failed_at' => $finalAttempt
                ? now()
                : null,
            'status_message' => mb_substr(
                '['.$code.'] '.$message,
                0,
                2000
            ),
        ])->save();
    }

    private function syncYouTubeCatalogue(
        ChannelVideoPublication $publication,
        array $result
    ): void {
        if (
            $publication->platform
                !== ChannelVideoPublication::PLATFORM_YOUTUBE
            || $publication->channelVideo === null
            || trim(
                (string) $publication->channelVideo->youtube_video_id
            ) !== ''
        ) {
            return;
        }

        $videoId = trim(
            (string) (
                $result['provider_post_id']
                ?? ''
            )
        );

        $url = trim(
            (string) (
                $result['provider_url']
                ?? ''
            )
        );

        if (
            strlen($videoId) !== 11
            || $url === ''
        ) {
            return;
        }

        $publication->channelVideo->forceFill([
            'youtube_video_id' => $videoId,
            'youtube_url' => $url,
        ])->saveQuietly();
    }
}
