<?php

namespace App\Http\Controllers\Api\Auth;

use App\Exceptions\AuthApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ConfirmRegistrationTotpRequest;
use App\Services\Auth\Registration\RegistrationCompletionService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class ConfirmRegistrationTotpController extends Controller
{
    public function __invoke(
        ConfirmRegistrationTotpRequest $request,
        RegistrationSessionService $registrations,
        RegistrationCompletionService $completion
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolveAny(
                $data['registration_id'],
                $data['registration_token']
            );

        if (! blank($session->social_provider)) {
            throw new AuthApiException(
                409,
                'SOCIAL_ONBOARDING_CONFIRM_REQUIRED',
                'Complete this registration through the matching social onboarding confirmation endpoint.'
            );
        }

        return response()->json([
            'success' => true,
            'data' =>
                $completion->complete(
                    $session,
                    $data['totp_code'],
                    $request
                ),
        ]);
    }
}
