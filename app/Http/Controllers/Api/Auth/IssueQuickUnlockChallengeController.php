<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\QuickUnlockChallengeRequest;
use App\Services\Security\QuickUnlock\QuickUnlockAuthenticationService;
use Illuminate\Http\JsonResponse;

class IssueQuickUnlockChallengeController extends Controller
{
    public function __invoke(
        QuickUnlockChallengeRequest $request,
        QuickUnlockAuthenticationService $service
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $service->issueChallenge(
                $request->validated('credential_id'),
                $request->validated('installation_id')
            ),
        ]);
    }
}
