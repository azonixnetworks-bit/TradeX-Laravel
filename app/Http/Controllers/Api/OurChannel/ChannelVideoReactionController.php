<?php

namespace App\Http\Controllers\Api\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OurChannel\ReactToChannelVideoRequest;
use App\Models\User;
use App\Services\OurChannel\OurChannelApiService;
use Illuminate\Http\JsonResponse;

class ChannelVideoReactionController extends Controller
{
    public function __invoke(
        ReactToChannelVideoRequest $request,
        string $channelVideo,
        OurChannelApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        $video = $service->resolve($user, $channelVideo);
        $reaction = $service->react(
            $user,
            $video,
            $request->validated('reaction')
        );

        return response()->json([
            'success' => true,
            'data' => ['reaction' => $reaction],
        ]);
    }
}
