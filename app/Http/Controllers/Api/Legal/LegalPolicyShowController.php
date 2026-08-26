<?php

namespace App\Http\Controllers\Api\Legal;

use App\Http\Controllers\Controller;
use App\Services\Legal\LegalPolicyApiService;
use Illuminate\Http\JsonResponse;

class LegalPolicyShowController extends Controller
{
    public function __invoke(
        string $policyType,
        LegalPolicyApiService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->currentPolicy($policyType),
        ]);
    }
}
