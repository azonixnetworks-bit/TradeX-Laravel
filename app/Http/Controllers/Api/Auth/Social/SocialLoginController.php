<?php

namespace App\Http\Controllers\Api\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Social\SocialLoginRequest;
use App\Services\Auth\Social\SocialLoginService;
use Illuminate\Http\JsonResponse;

class SocialLoginController extends Controller
{
    public function __invoke(SocialLoginRequest $request, SocialLoginService $service, string $provider): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->login(strtolower($provider), $request->validated(), $request),
        ]);
    }
}
