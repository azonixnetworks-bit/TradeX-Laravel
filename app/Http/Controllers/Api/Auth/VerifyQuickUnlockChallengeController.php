<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\QuickUnlockVerifyRequest;
use App\Services\Security\QuickUnlock\QuickUnlockAuthenticationService;
use Illuminate\Http\JsonResponse;

class VerifyQuickUnlockChallengeController extends Controller
{
    public function __invoke(
        QuickUnlockVerifyRequest $request,
        QuickUnlockAuthenticationService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->verifyChallenge(
                $request->validated(),
                $request
            ),
        ]);
    }
}
