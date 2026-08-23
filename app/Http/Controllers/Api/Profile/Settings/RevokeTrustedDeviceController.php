<?php

namespace App\Http\Controllers\Api\Profile\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Profile\Settings\RevokeTrustedDeviceRequest;
use App\Models\User;
use App\Services\Profile\AccountSettingsApiService;
use Illuminate\Http\JsonResponse;

class RevokeTrustedDeviceController extends Controller
{
    public function __invoke(
        RevokeTrustedDeviceRequest $request,
        string $device,
        AccountSettingsApiService $service
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $service->revokeRemoteDevice(
                $user,
                $device,
                (string) $request->validated('totp_code'),
                $request
            ),
        ]);
    }
}
