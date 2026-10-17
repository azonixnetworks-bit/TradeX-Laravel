<?php

namespace App\Http\Controllers\Api\TradeIdeas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TradeIdeas\TradeIdeaIndexRequest;
use App\Models\User;
use App\Services\TradeIdeas\TradeIdeaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

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

        $service->assertPremium(
            $user
        );

        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.trade_ideas_list_cache_seconds',
                10
            )
        );

        $cacheKey = 'tradex:api:trade-ideas:v1:'
            .sha1(
                json_encode(
                    [
                        'user_version' => [
                            $user->getKey(),
                            $user->updated_at?->getTimestamp(),
                            $user->account_type,
                        ],
                        'page' => (int) $request->query('page', 1),
                        'filters' => $filters,
                    ],
                    JSON_THROW_ON_ERROR
                )
            );

        $payload = $ttlSeconds === 0
            ? $this->payload($service, $user, $filters)
            : Cache::remember(
                $cacheKey,
                now()->addSeconds($ttlSeconds),
                fn (): array =>
                    $this->payload(
                        $service,
                        $user,
                        $filters
                    )
            );

        return response()->json($payload);
    }
    private function payload(
        TradeIdeaApiService $service,
        User $user,
        array $filters
    ): array {
        $paginator = $service->index(
            $user,
            $filters
        );

        return [
            'success' => true,
            'data' => [
                'trade_ideas' =>
                    $paginator
                        ->getCollection()
                        ->map(
                            fn ($tradeIdea): array =>
                                $service->summary(
                                    $tradeIdea,
                                    $user
                                )
                        )
                        ->values()
                        ->all(),

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
        ];
    }

}
