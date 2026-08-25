<?php

namespace App\Http\Controllers\Api\Auth\PasswordRecovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordRecovery\PasswordRecoverySessionRequest;
use App\Services\Auth\PasswordRecovery\PasswordRecoveryFlowService;
use Illuminate\Http\JsonResponse;

class ResendPasswordRecoveryWhatsAppController extends Controller
{
    public function __invoke(
        PasswordRecoverySessionRequest $request,
        PasswordRecoveryFlowService $service
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $service->resendWhatsApp(
                (string) $data['recovery_id'],
                (string) $data['recovery_token']
            ),
        ], 202);
    }
}
