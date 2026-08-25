<?php

namespace App\Http\Controllers\Api\Auth\PasswordRecovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordRecovery\VerifyPasswordRecoveryTotpRequest;
use App\Services\Auth\PasswordRecovery\PasswordRecoveryFlowService;
use Illuminate\Http\JsonResponse;

class VerifyPasswordRecoveryTotpController extends Controller
{
    public function __invoke(
        VerifyPasswordRecoveryTotpRequest $request,
        PasswordRecoveryFlowService $service
    ): JsonResponse {
        $data = $request->validated();

        return response()->json([
            'success' => true,
            'data' => $service->verifyTotp(
                (string) $data['recovery_id'],
                (string) $data['recovery_token'],
                (string) $data['totp_code'],
                $request
            ),
        ]);
    }
}
