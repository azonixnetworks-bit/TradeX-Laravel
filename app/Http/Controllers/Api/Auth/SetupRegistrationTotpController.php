<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Services\Auth\Registration\RegistrationSessionService;
use App\Services\Auth\Registration\TotpProvisioningService;
use Illuminate\Http\JsonResponse;

class SetupRegistrationTotpController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
        RegistrationSessionService $registrations,
        TotpProvisioningService $totp
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolvePending(
                $data['registration_id'],
                $data['registration_token']
            );

        return response()->json([
            'success' => true,
            'data' =>
                $totp->setup(
                    $session
                ),
        ]);
    }
}
