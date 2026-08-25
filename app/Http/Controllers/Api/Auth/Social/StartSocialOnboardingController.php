<?php

namespace App\Http\Controllers\Api\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Social\StartSocialOnboardingRequest;
use App\Services\Auth\Social\SocialOnboardingService;
use Illuminate\Http\JsonResponse;

class StartSocialOnboardingController extends Controller
{
    public function __invoke(StartSocialOnboardingRequest $request, SocialOnboardingService $service, string $provider): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->start(strtolower($provider), $request->validated(), $request),
        ], 201);
    }
}
