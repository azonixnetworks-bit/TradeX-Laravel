<?php

namespace App\Http\Controllers\Api\OurChannel;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OurChannel\OurChannelApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelVideoShowController extends Controller
{
    public function __invoke(
        Request $request,
        string $channelVideo,
        OurChannelApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $video = $service->resolve(
            $user,
            $channelVideo
        );

        return response()->json([
            'success' => true,
            'data' => [
                'video' => $service->detail(
                    $video,
                    $user
                ),
            ],
        ]);
    }
}
