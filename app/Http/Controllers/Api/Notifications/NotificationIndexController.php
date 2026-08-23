<?php

namespace App\Http\Controllers\Api\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notifications\NotificationIndexRequest;
use App\Models\User;
use App\Services\Notifications\NotificationApiService;
use Illuminate\Http\JsonResponse;

class NotificationIndexController extends Controller
{
    public function __invoke(
        NotificationIndexRequest $request,
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
            'data' => $service->index(
                $user,
                $request->validated()
            ),
        ]);
    }
}
