<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RefreshRequest;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\JsonResponse;

class RefreshController extends Controller
{
    public function __invoke(
        RefreshRequest $request,
        RefreshTokenService $refreshTokens
    ): JsonResponse {
        $validated =
            $request->validated();

        return response()->json([
            'success' => true,
            'data' => $refreshTokens->rotate(
                $validated['refresh_token'],
                $validated['installation_id'],
                $request,
                $validated['quick_unlock_totp_code'] ?? null
            ),
        ]);
    }
}
