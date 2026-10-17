<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TradeIdeas\ReactToTradeIdeaRequest;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Http\JsonResponse;

class TradeIdeaReactionController extends Controller
{
    public function __invoke(
        ReactToTradeIdeaRequest $request,
        string $tradeIdea,
        TradeIdeaApiService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $idea = $service->resolve($user, $tradeIdea);
        $reaction = $service->react(
            $user,
            $idea,
            $request->validated('reaction')
        );

        return response()->json([
            'success' => true,
            'data' => ['reaction' => $reaction],
        ]);
    }
}
