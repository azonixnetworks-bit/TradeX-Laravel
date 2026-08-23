<?php

namespace App\Http\Controllers\Api\Affiliate;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Affiliates\AffiliateApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateStageIndexController extends Controller
{
    public function __invoke(
        Request $request,
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
            'data' => $service->stages(
                $user
            ),
        ]);
    }
}
