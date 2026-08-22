<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Models\TradeIdea;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use App\Services\TradeIdeas\UserTradeIdeaShareCardService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class UserTradeIdeaShareCardController extends Controller
{
    public function svg(
        Request $request,
        TradeIdea $tradeIdea,
        UserTradeIdeaShareCardService $shareCardService
    ): Response {
        $user =
            $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $variant =
            strtolower(
                trim(
                    (string) $request->query(
                        'variant',
                        TradeIdeaShareCardService::VARIANT_PORTRAIT
                    )
                )
            );

        abort_unless(
            in_array(
                $variant,
                $shareCardService->variants(),
                true
            ),
            422,
            'Unsupported Share Card variant.'
        );

        try {
            $svg =
                $shareCardService->render(
                    user:
                        $user,

                    tradeIdea:
                        $tradeIdea,

                    variant:
                        $variant
                );
        } catch (AuthorizationException $exception) {
            abort(
                403,
                $exception->getMessage()
            );
        } catch (RuntimeException $exception) {
            abort(
                409,
                $exception->getMessage()
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
                    .$tradeIdea->id
                    .'-'
                    .$variant
                    .'.svg"',
            ]
        );
    }
}
