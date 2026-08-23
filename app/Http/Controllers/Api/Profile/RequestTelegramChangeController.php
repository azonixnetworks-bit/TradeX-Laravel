<?php

namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\RequestTelegramChangeRequest;
use App\Models\User;
use App\Services\Profile\ProfileContactChangeService;
use Illuminate\Http\JsonResponse;

class RequestTelegramChangeController extends Controller
{
    public function __invoke(
        RequestTelegramChangeRequest $request,
        ProfileContactChangeService $contacts
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' =>
                $contacts->requestTelegram(
                    $user,
                    $data['new_telegram_username'],
                    $data['totp_code'],
                    $request
                ),
        ]);
    }
}
