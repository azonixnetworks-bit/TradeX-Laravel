<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TradeIdeas\TradeIdeaShareActionRequest;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use App\Services\TradeIdeas\TradeIdeaShareCardService;
use Illuminate\Http\JsonResponse;

class TradeIdeaShareActionController extends Controller
{
    public function __invoke(
        TradeIdeaShareActionRequest $request,
        string $tradeIdea,
        TradeIdeaApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $resolved = $service->resolve(
            $user,
            $tradeIdea
        );

        $validated = $request->validated();

        $action = $service->prepareShareAction(
            $user,
            $resolved,
            $validated['channel'],
            $validated['variant']
                ?? TradeIdeaShareCardService::VARIANT_PORTRAIT,
            $request
        );

        return response()->json([
            'success' => true,
            'data' => [
                'trade_idea_uuid' =>
                    $resolved->uuid,

                'share_action' =>
                    $action,
            ],
        ]);
    }
}
