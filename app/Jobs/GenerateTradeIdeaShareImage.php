<?php

namespace App\Jobs;

use App\Models\TradeIdea;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTradeIdeaShareImage implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 600;

    public function __construct(
        public readonly int $tradeIdeaId,
        public readonly ?int $userId = null
    ) {
    }

    public function uniqueId(): string
    {
        return 'trade-idea-share-card:'
            .$this->tradeIdeaId
            .':'
            .(
                $this->userId
                ?? 'global'
            );
    }

    public function handle(
        TradeIdeaShareCardService $service
    ): void {
        $tradeIdea =
            TradeIdea::query()
                ->findOrFail(
                    $this->tradeIdeaId
                );

        $user =
            $this->userId !== null
                ? User::query()
                    ->findOrFail(
                        $this->userId
                    )
                : null;

        $service->cache(
            $tradeIdea,
            $user
        );
    }
}
