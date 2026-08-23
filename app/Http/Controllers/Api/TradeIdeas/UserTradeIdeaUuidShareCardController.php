<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use App\Services\TradeIdeas\UserTradeIdeaShareCardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class UserTradeIdeaUuidShareCardController extends Controller
{
    public function __invoke(
        Request $request,
        string $tradeIdea,
        TradeIdeaApiService $apiService,
        UserTradeIdeaShareCardService $shareCardService
    ): Response {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $resolved = $apiService->resolve(
            $user,
            $tradeIdea
        );

        $variant = strtolower(
            trim(
                (string) $request->query(
                    'variant',
                    TradeIdeaShareCardService::VARIANT_PORTRAIT
                )
            )
        );

        if (
            ! in_array(
                $variant,
                $shareCardService->variants(),
                true
            )
        ) {
            throw new AuthApiException(
                422,
                'TRADE_IDEA_SHARE_CARD_VARIANT_INVALID',
                'Unsupported Trade Idea share-card variant.'
            );
        }

        try {
            $svg = $shareCardService->render(
                user: $user,
                tradeIdea: $resolved,
                variant: $variant
            );
        } catch (AuthorizationException $exception) {
            throw new AuthApiException(
                403,
                'TRADE_IDEA_PREMIUM_REQUIRED',
                'Premium access is required to share Trade Ideas.'
            );
        } catch (RuntimeException $exception) {
            throw new AuthApiException(
                409,
                'TRADE_IDEA_SHARE_CARD_UNAVAILABLE',
                'Trade Idea share card is not available.'
            );
        }

        return response(
            $svg,
            200,
            [
                'Content-Type' =>
                    'image/svg+xml; charset=UTF-8',

                'Cache-Control' =>
                    'private, no-store, no-cache, must-revalidate, max-age=0',

                'Pragma' =>
                    'no-cache',

                'Expires' =>
                    '0',

                'X-Content-Type-Options' =>
                    'nosniff',

                'Content-Disposition' =>
                    'inline; filename="tradex-trade-idea-'
                    .$resolved->uuid
                    .'-'
                    .$variant
                    .'.svg"',

                'X-TradeX-Content-Protection' =>
                    'premium-private',
            ]
        );
    }
}
