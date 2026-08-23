<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifications\NotificationApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarkAllNotificationsReadController extends Controller
{
    public function __invoke(
        Request $request,
        NotificationApiService $service
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $service->markAllRead(
                $user
            ),
        ]);
    }
}
