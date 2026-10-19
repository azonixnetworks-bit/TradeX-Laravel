<?php

namespace App\Http\Controllers\Api\Security;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Security\QuickUnlock\QuickUnlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickUnlockForgetController extends Controller
{
    public function __invoke(Request $request, QuickUnlockService $service): JsonResponse
    {
        $user = $request->user();
        $device = $request->attributes->get('tradex_user_device');

        if (! $user instanceof User || ! $device instanceof UserDevice) {
            throw new AuthApiException(
                401,
                'QUICK_UNLOCK_SESSION_INVALID',
                'A valid trusted mobile session is required.'
            );
        }

        $service->forget($user, $device, $request);

        return response()->json([
            'success' => true,
            'message' => 'Quick login was removed from this device.',
        ]);
    }
}
