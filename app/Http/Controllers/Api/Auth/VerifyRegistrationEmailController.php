<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\VerifyRegistrationEmailOtpRequest;
use App\Models\CustomerOnboardingInvitation;
use App\Services\Auth\Registration\RegistrationOtpService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class VerifyRegistrationEmailController extends Controller
{
    public function __invoke(
        VerifyRegistrationEmailOtpRequest $request,
        RegistrationSessionService $registrations,
        RegistrationOtpService $otp
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolvePending(
                $data['registration_id'],
                $data['registration_token']
            );

        $otp->verifyEmail(
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
                        CustomerOnboardingInvitation::STAGE_EMAIL_VERIFIED,
                ]);
        }

        $whatsAppSent = true;

        try {
            $otp->ensureWhatsAppIssued(
                $session,
                $request
            );
        } catch (\App\Exceptions\AuthApiException) {
            $whatsAppSent = false;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email_verified' =>
                    true,

                'whatsapp_otp_sent' =>
                    $whatsAppSent,

                'next_action' =>
                    $whatsAppSent
                        ? 'VERIFY_WHATSAPP_OTP'
                        : 'RESEND_WHATSAPP_OTP',
            ],
        ]);
    }
}
