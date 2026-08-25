<?php

namespace App\Http\Controllers\Api\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Social\ConfirmSocialOnboardingRequest;
use App\Services\Auth\Social\SocialOnboardingService;
use Illuminate\Http\JsonResponse;

class ConfirmSocialOnboardingController extends Controller
{
    public function __invoke(ConfirmSocialOnboardingRequest $request, SocialOnboardingService $service, string $provider): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $service->confirm(strtolower($provider), $request->validated(), $request),
        ]);
    }
}
