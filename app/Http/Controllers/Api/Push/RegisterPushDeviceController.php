<?php

namespace App\Http\Controllers\Api\Push;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Push\RegisterPushDeviceRequest;
use App\Models\User;
use App\Services\Push\PushDeviceRegistrationService;
use Illuminate\Http\JsonResponse;

class RegisterPushDeviceController extends Controller
{
    public function __invoke(
        RegisterPushDeviceRequest $request,
        PushDeviceRegistrationService $service
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        $data = $service->register(
            $user,
            $request,
            (string) $request->validated('token')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ], $data['created'] ? 201 : 200);
    }
}
