<?php

namespace App\Jobs;

use App\Exceptions\TradeIdeaDistributionException;
use App\Models\TradeIdeaDistribution;
use App\Services\TradeIdeas\TelegramTradeIdeaClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendTelegramTradeIdea implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries;

    public int $uniqueFor = 900;

    public function __construct(
        public readonly int $distributionId
    ) {
        $this->tries = max(
            1,
            (int) config(
                'tradex_trade_ideas.jobs.tries',
                3
            )
        );
    }

    public function uniqueId(): string
    {
        return 'trade-idea-telegram:'
            .$this->distributionId;
    }

    public function backoff(): array
    {
        return (array) config(
            'tradex_trade_ideas.jobs.backoff_seconds',
            [
                15,
                60,
                180,
            ]
        );
    }

    public function handle(
        TelegramTradeIdeaClient $client
    ): void {
        $distribution =
            TradeIdeaDistribution::query()
                ->findOrFail(
                    $this->distributionId
                );

        if ($distribution->isSent()) {
            return;
        }

        if (
            $distribution->channel
            !== TradeIdeaDistribution::CHANNEL_TELEGRAM
        ) {
            return;
        }

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_PROCESSING,

            'attempt_count' =>
                $distribution->attempt_count
                + 1,

            'started_at' =>
                now(),

            'failed_at' =>
                null,

            'last_error_code' =>
                null,

            'last_error_message' =>
                null,
        ])->save();

        try {
            $result =
                $client->send(
                    $distribution
                );

            $distribution->forceFill([
                'status' =>
                    TradeIdeaDistribution::STATUS_SENT,

                'provider_message_id' =>
                    $result['provider_message_id']
                    ?? null,

                'response_meta' =>
                    $result['response_meta']
                    ?? null,

                'sent_at' =>
                    now(),

                'failed_at' =>
                    null,

                'last_error_code' =>
                    null,

                'last_error_message' =>
                    null,
            ])->save();
        } catch (
            TradeIdeaDistributionException $exception
        ) {
            $this->markFailed(
                $distribution,
                $exception->providerCode,
                $exception->getMessage()
            );

            throw $exception;
        } catch (Throwable $exception) {
            $safeException =
                new TradeIdeaDistributionException(
                    'TELEGRAM',
                    'UNEXPECTED',
                    'Unexpected Telegram distribution failure.'
                );

            $this->markFailed(
                $distribution,
                $safeException->providerCode,
                $safeException->getMessage()
            );

            /*
            | Do not rethrow the raw HTTP/network exception because Telegram's
            | Bot API token is part of the request URL and could otherwise be
            | persisted into queue failure logs.
            */
            throw $safeException;
        }
    }

    private function markFailed(
        TradeIdeaDistribution $distribution,
        string $code,
        string $safeMessage
    ): void {
        $finalAttempt =
            $this->attempts()
            >= $this->tries;

        $distribution->forceFill([
            'status' =>
                $finalAttempt
                    ? TradeIdeaDistribution::STATUS_FAILED
                    : TradeIdeaDistribution::STATUS_PENDING,

            'failed_at' =>
                $finalAttempt
                    ? now()
                    : null,

            'last_error_code' =>
                mb_substr(
                    $code,
                    0,
                    100
                ),

            'last_error_message' =>
                mb_substr(
                    $safeMessage,
                    0,
                    2000
                ),
        ])->save();
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $distribution =
            TradeIdeaDistribution::query()
                ->find(
                    $this->distributionId
                );

        if (
            $distribution === null
            || $distribution->isSent()
        ) {
            return;
        }

        $distribution->forceFill([
            'status' =>
                TradeIdeaDistribution::STATUS_FAILED,

            'failed_at' =>
                now(),

            'last_error_code' =>
                $distribution->last_error_code
                ?? 'JOB_FAILED',

            'last_error_message' =>
                $distribution->last_error_message
                ?? 'Telegram distribution job failed after all retries.',
        ])->save();
    }
}
