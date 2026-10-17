<?php

namespace App\Http\Controllers\Api\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OurChannel\ChannelVideoIndexRequest;
use App\Models\User;
use App\Services\OurChannel\OurChannelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ChannelVideoIndexController extends Controller
{
    public function __invoke(
        ChannelVideoIndexRequest $request,
        OurChannelApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $filters = $request->validated();

        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.our_channel_list_cache_seconds',
                30
            )
        );

        $cacheKey = 'tradex:api:our-channel:v1:'
            .sha1(
                json_encode(
                    [
                        'user_version' => [
                            $user->getKey(),
                            $user->updated_at?->getTimestamp(),
                            $user->status,
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
        OurChannelApiService $service,
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
                'videos' => $paginator
                    ->getCollection()
                    ->map(
                        fn ($video): array =>
                            $service->summary($video, $user)
                    )
                    ->values()
                    ->all(),
                'filters' => [
                    'language_code' =>
                        $filters['language_code']
                        ?? null,
                    'featured' =>
                        $filters['featured']
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
