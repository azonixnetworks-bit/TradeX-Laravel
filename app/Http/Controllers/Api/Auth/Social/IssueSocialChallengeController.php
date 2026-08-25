<?php

namespace App\Http\Controllers\Api\Auth\Social;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\Social\SocialChallengeRequest;
use App\Services\Auth\Social\SocialChallengeService;
use Illuminate\Http\JsonResponse;

class IssueSocialChallengeController extends Controller
{
    public function __invoke(
        SocialChallengeRequest $request,
        SocialChallengeService $challenges,
        string $provider
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $challenges->issue(
                strtolower($provider),
                (string) $request->validated('purpose'),
                $request
            ),
        ]);
    }
}
