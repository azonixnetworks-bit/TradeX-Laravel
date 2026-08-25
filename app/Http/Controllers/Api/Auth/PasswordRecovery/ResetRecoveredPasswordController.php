<?php

namespace App\Http\Controllers\Api\Auth\PasswordRecovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\PasswordRecovery\ResetRecoveredPasswordRequest;
use App\Services\Auth\PasswordRecovery\PasswordRecoveryResetService;
use Illuminate\Http\JsonResponse;

class ResetRecoveredPasswordController extends Controller
{
    public function __invoke(
        ResetRecoveredPasswordRequest $request,
        PasswordRecoveryResetService $service
    ): JsonResponse {
        $data = $request->validated();

        $service->reset(
            (string) $data['recovery_id'],
            (string) $data['recovery_token'],
            (string) $data['reset_token'],
            (string) $data['password'],
            $request
        );

        return response()->json([
            'success' => true,
            'data' => [
                'completed' => true,
                'next_action' => 'LOGIN',
            ],
        ]);
    }
}
