<?php

namespace App\Http\Controllers\Api\Auth\PasswordRecovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordRecovery\VerifyPasswordRecoveryOtpRequest;
use App\Services\Auth\PasswordRecovery\PasswordRecoveryFlowService;
use Illuminate\Http\JsonResponse;

class VerifyPasswordRecoveryWhatsAppController extends Controller
{
    public function __invoke(
        VerifyPasswordRecoveryOtpRequest $request,
        PasswordRecoveryFlowService $service
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $service->verifyWhatsApp(
                (string) $data['recovery_id'],
                (string) $data['recovery_token'],
                (string) $data['otp_code'],
                $request
            ),
        ]);
    }
}
