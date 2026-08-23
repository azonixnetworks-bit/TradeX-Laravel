<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyRegistrationOtpRequest;
use App\Services\Auth\Registration\RegistrationOtpService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class VerifyRegistrationWhatsAppController extends Controller
{
    public function __invoke(
        VerifyRegistrationOtpRequest $request,
        RegistrationSessionService $registrations,
        RegistrationOtpService $otp
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolvePending(
                $data['registration_id'],
                $data['registration_token']
            );

        $otp->verifyWhatsApp(
            $session,
            $data['otp_code'],
            $request
        );

        return response()->json([
            'success' => true,
            'data' => [
                'whatsapp_verified' =>
                    true,

                'next_action' =>
                    'SETUP_TOTP',
            ],
        ]);
    }
}
