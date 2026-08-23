<?php

namespace App\Http\Controllers\Api\Education;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Education\EducationPlaybackService;
use App\Services\Education\EducationResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IssueVideoPlaybackTokenController extends Controller
{
    public function __invoke(
        Request $request,
        string $video,
        EducationResolver $resolver,
        EducationPlaybackService $playback
    ): JsonResponse {
        $user =
            $request->user();

        $device =
            $request->attributes
                ->get(
                    'tradex_user_device'
                );

        abort_unless(
            $user instanceof User
            && $device instanceof UserDevice,
            401,
            'Authenticated TradeX device session is required.'
        );

        $model =
            $resolver->video(
                $video
            );

        $model->loadMissing(
            'lesson.module.course'
        );

        return response()->json([
            'success' => true,
            'data' =>
                $playback->issue(
                    $user,
                    $device,
                    $model,
                    (string)
                        $request
                            ->attributes
                            ->get(
                                'tradex_session_uuid'
                            ),
                    $request
                ),
        ]);
    }
}
