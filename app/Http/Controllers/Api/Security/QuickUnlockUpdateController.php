<?php

namespace App\Http\Controllers\Api\Security;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Security\UpdateQuickUnlockRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Security\QuickUnlock\QuickUnlockService;
use Illuminate\Http\JsonResponse;

class QuickUnlockUpdateController extends Controller
{
    public function __invoke(
        UpdateQuickUnlockRequest $request,
        QuickUnlockService $service
    ): JsonResponse {
        $user = $request->user();

        $device = $request->attributes->get(
            'tradex_user_device'
        );

        if (
            ! $user instanceof User
            || ! $device instanceof UserDevice
        ) {
            throw new AuthApiException(
                401,
                'QUICK_UNLOCK_SESSION_INVALID',
                'A valid trusted mobile session is required.'
            );
        }

        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $service->update(
                $user,
                $device,
                (bool) $data['enabled'],
                $data['method'] ?? null,
                (string) $data['totp_code'],
                $request
            ),
        ]);
    }
}
