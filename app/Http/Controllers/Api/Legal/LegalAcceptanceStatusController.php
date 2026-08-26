<?php

namespace App\Http\Controllers\Api\Legal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Legal\LegalPolicyApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegalAcceptanceStatusController extends Controller
{
    public function __invoke(
        Request $request,
        LegalPolicyApiService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $service->acceptanceStatus($user),
        ]);
    }
}
