<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Services\Auth\Registration\RegistrationOtpService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class ResendRegistrationWhatsAppOtpController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
        RegistrationSessionService $registrations,
        RegistrationOtpService $otp
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolvePending(
                $data['registration_id'],
                $data['registration_token']
            );

        if (
            $session->whatsapp_verified_at
            !== null
        ) {
            return response()->json([
                'success' => true,
                'message' =>
                    'WhatsApp is already verified.',
                'data' => [
                    'next_action' =>
                        'SETUP_TOTP',
                ],
            ]);
        }

        $otp->issueWhatsApp(
            $session,
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'WhatsApp verification code sent.',
        ]);
    }
}
