<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\CustomerDeviceSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceIndexController extends Controller
{
    public function __invoke(
        Request $request,
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

        return response()->json([
            'success' => true,
            'data' => [
                'max_trusted_devices' => $devices->maxTrustedDevices(),
                'devices' => $devices->activeTrustedDevices(
                    $user,
                    $currentDevice
                )->values(),
            ],
        ]);
    }
}
