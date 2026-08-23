<?php

namespace App\Http\Controllers\Api\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Affiliates\AffiliateApiService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\Affiliate\AffiliateCommissionIndexRequest;

class AffiliateCommissionIndexController extends Controller
{
    public function __invoke(
        AffiliateCommissionIndexRequest $request,
        AffiliateApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->commissions(
                $user,
                $request->validated()
            ),
        ]);
    }
}
