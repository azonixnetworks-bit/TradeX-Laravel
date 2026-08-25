<?php

namespace App\Http\Controllers\Api\Auth\PasswordRecovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordRecovery\StartPasswordRecoveryRequest;
use App\Services\Auth\PasswordRecovery\PasswordRecoverySessionService;
use Illuminate\Http\JsonResponse;

class StartPasswordRecoveryController extends Controller
{
    public function __invoke(
        StartPasswordRecoveryRequest $request,
        PasswordRecoverySessionService $service
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $service->start(
                (string) $data['email'],
                (string) $data['whatsapp_number'],
                $request
            ),
        ], 202);
    }
}
