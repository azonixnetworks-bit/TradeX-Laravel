<?php

namespace App\Http\Controllers\Api\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Affiliate\AffiliateEarningsSeriesRequest;
use App\Models\User;
use App\Services\Affiliates\AffiliateApiService;
use Illuminate\Http\JsonResponse;

class AffiliateEarningsSeriesController extends Controller
{
    public function __invoke(
        AffiliateEarningsSeriesRequest $request,
        AffiliateApiService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $service->earningsSeries(
                $user,
                (int) ($request->validated('months') ?? 12)
            ),
        ]);
    }
}
