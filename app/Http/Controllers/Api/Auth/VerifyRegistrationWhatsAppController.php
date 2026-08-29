<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyRegistrationOtpRequest;
use App\Models\CustomerOnboardingInvitation;
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

        $session->refresh();

        if ($session->isAdminInvite()) {
            CustomerOnboardingInvitation::query()
                ->whereKey(
                    $session->customer_onboarding_invitation_id
                )
                ->where(
                    'status',
                    CustomerOnboardingInvitation::STATUS_PENDING
                )
                ->update([
                    'onboarding_stage' =>
                        CustomerOnboardingInvitation::STAGE_WHATSAPP_VERIFIED,
                ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'whatsapp_verified' => true,
                'next_action' =>
                    $session->isAdminInvite()
                        ? 'SET_PASSWORD'
                        : 'SETUP_TOTP',
            ],
        ]);
    }
}
