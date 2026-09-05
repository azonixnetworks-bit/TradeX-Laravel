<?php

namespace App\Http\Controllers\Api\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\DashboardApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        DashboardApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $data = $service->dashboard(
            $user
        );

        /*
         * Affiliate referral URLs are a premium entitlement benefit.
         * Keep the permanent internal referral code stable for compatibility,
         * but do not expose an actionable affiliate URL until the authoritative
         * EDUCATION_PREMIUM entitlement is active.
         */
        if (! $user->canAccessPremium()) {
            $data['referral']['url'] = null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
