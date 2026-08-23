<?php

namespace App\Services\TradeIdeas;

use App\Models\TradeIdea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use RuntimeException;

class UserTradeIdeaShareCardService
{
    public function __construct(
        private readonly TradeIdeaShareCardService $shareCardService
    ) {
    }

    public function variants(): array
    {
        return $this->shareCardService->variants();
    }

    public function render(
        User $user,
        TradeIdea $tradeIdea,
        string $variant = TradeIdeaShareCardService::VARIANT_PORTRAIT
    ): string {
        $this->authorizeUser(
            $user
        );

        $this->authorizeTradeIdea(
            $tradeIdea
        );

        $referralCode =
            trim(
                (string) $user->referral_code
            );

        if ($referralCode === '') {
            throw new RuntimeException(
                'User referral code is unavailable.'
            );
        }

        return $this->shareCardService->renderSvg(
            tradeIdea:
                $tradeIdea,

            referralCode:
                $user,

            affiliateUrl:
                null,

            variant:
                $variant
        );
    }

    private function authorizeUser(
        User $user
    ): void {
        if (! $user->canAccessPremium()) {
            throw new AuthorizationException(
                'Premium access is required to share Trade Ideas.'
            );
        }
    }

    private function authorizeTradeIdea(
        TradeIdea $tradeIdea
    ): void {
        if (
            ! $tradeIdea->isPublished()
            && ! $tradeIdea->isClosed()
        ) {
            throw new AuthorizationException(
                'This Trade Idea is not available for user sharing.'
            );
        }
    }
}
