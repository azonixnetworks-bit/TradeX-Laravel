<?php

namespace App\Http\Controllers\Api\Push;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Push\PushDeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDeviceStatusController extends Controller
{
    public function __invoke(
        Request $request,
        PushDeviceRegistrationService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => $service->status($user, $request),
        ]);
    }
}
