<?php

namespace App\Http\Controllers\Api\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OurChannel\ChannelVideoIndexRequest;
use App\Models\User;
use App\Services\OurChannel\OurChannelApiService;
use Illuminate\Http\JsonResponse;

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

        $paginator = $service->index(
            $user,
            $filters
        );

        return response()->json([
            'success' => true,
            'data' => [
                'videos' => $paginator
                    ->getCollection()
                    ->map(
                        fn ($video): array =>
                            $service->summary($video)
                    )
                    ->values(),
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
        ]);
    }
}
