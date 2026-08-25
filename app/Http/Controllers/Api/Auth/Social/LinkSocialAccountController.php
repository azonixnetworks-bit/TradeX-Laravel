<?php

namespace App\Http\Controllers\Api\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Social\LinkSocialAccountRequest;
use App\Models\User;
use App\Services\Auth\Social\SocialLinkService;
use Illuminate\Http\JsonResponse;

class LinkSocialAccountController extends Controller
{
    public function __invoke(LinkSocialAccountRequest $request, SocialLinkService $service, string $provider): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $service->link($user, strtolower($provider), $request->validated(), $request),
        ]);
    }
}
