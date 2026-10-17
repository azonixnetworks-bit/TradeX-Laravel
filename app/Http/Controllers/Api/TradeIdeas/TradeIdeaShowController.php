<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TradeIdeaShowController extends Controller
{
    public function __invoke(
        Request $request,
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

        return response()->json([
            'success' => true,
            'data' => [
                'trade_idea' =>
                    $service->detail(
                        $resolved,
                        $user
                    ),
            ],
        ]);
    }
}
