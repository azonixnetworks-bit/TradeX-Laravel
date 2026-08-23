<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\ConfirmProfileContactChangeRequest;
use App\Models\User;
use App\Services\Profile\ProfileContactChangeService;
use Illuminate\Http\JsonResponse;

class ConfirmTelegramChangeController extends Controller
{
    public function __invoke(
        ConfirmProfileContactChangeRequest $request,
        ProfileContactChangeService $contacts
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $contacts->confirmTelegram(
            $user,
            $request->validated(),
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Telegram username updated.',
            'data' => [
                'telegram_username' =>
                    $user->fresh()
                        ->profile
                        ?->telegram_username,
            ],
        ]);
    }
}
