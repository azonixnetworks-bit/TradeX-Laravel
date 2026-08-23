<?php

namespace App\Http\Controllers\Api\Profile\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Profile\AccountSettingsApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrustedDeviceIndexController extends Controller
{
    public function __invoke(Request $request, AccountSettingsApiService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return response()->json([
            'success' => true,
            'data' => $service->devices($user, $request),
        ]);
    }
}
