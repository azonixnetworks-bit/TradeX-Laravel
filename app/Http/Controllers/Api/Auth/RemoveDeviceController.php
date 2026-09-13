<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\CustomerDeviceSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoveDeviceController extends Controller
{
    public function __invoke(
        Request $request,
        string $deviceUuid,
        CustomerDeviceSecurityService $devices
    ): JsonResponse {
        $user = $request->user();
        $currentDevice = $request->attributes
            ->get('tradex_user_device');

        abort_unless(
            $user instanceof User
            && $currentDevice instanceof UserDevice,
            401,
            'Unauthenticated.'
        );

        $devices->removeOtherDevice(
            $user,
            $currentDevice,
            $deviceUuid,
            $request
        );

        return response()->json([
            'success' => true,
            'message' => 'Trusted device removed and its active sessions were revoked.',
        ]);
    }
}
