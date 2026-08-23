<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TradeIdeas\TradeIdeaIndexRequest;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Http\JsonResponse;

class TradeIdeaIndexController extends Controller
{
    public function __invoke(
        TradeIdeaIndexRequest $request,
        TradeIdeaApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $filters = $request->validated();

        $paginator = $service->index(
            $user,
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => [
                'trade_ideas' =>
                    $paginator
                        ->getCollection()
                        ->map(
                            fn ($tradeIdea): array =>
                                $service->summary(
                                    $tradeIdea
                                )
                        )
                        ->values(),

                'filters' => [
                    'category' =>
                        $filters['category']
                        ?? null,

                    'market_region' =>
                        $filters['market_region']
                        ?? null,

                    'direction' =>
                        $filters['direction']
                        ?? null,

                    'timeframe' =>
                        $filters['timeframe']
                        ?? null,

                    'status' =>
                        $filters['status']
                        ?? null,

                    'symbol' =>
                        $filters['symbol']
                        ?? null,
                ],
            ],
            'meta' => [
                'current_page' =>
                    $paginator->currentPage(),

                'per_page' =>
                    $paginator->perPage(),

                'total' =>
                    $paginator->total(),

                'last_page' =>
                    $paginator->lastPage(),
            ],
        ]);
    }
}
