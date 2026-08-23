<?php

namespace App\Http\Controllers\Api\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Affiliate\AffiliateShareActionRequest;
use App\Models\User;
use App\Services\Affiliates\AffiliateApiService;
use Illuminate\Http\JsonResponse;

class AffiliateShareActionController extends Controller
{
    public function __invoke(
        AffiliateShareActionRequest $request,
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
            'data' => $service->shareAction(
                $user,
                (string) $request->validated('channel'),
                $request
            ),
        ]);
    }
}
