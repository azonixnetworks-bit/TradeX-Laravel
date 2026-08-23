<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutController extends Controller
{
    public function __invoke(
        Request $request,
        SessionRevocationService $sessions
    ): JsonResponse {
        $user = $request->user();
        $device = $request->attributes
            ->get('tradex_user_device');

        abort_unless(
            $user instanceof User
            && $device instanceof UserDevice,
            401,
            'Unauthenticated.'
        );

        $sessions->logoutCurrent(
            $user,
            $device,
            (string) $request->attributes
                ->get(
                    'tradex_session_uuid'
                ),
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Current session logged out.',
        ]);
    }
}
