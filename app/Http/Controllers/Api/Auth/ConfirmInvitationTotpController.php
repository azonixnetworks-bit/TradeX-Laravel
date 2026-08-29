<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ConfirmRegistrationTotpRequest;
use App\Services\Auth\Registration\InviteRegistrationService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class ConfirmInvitationTotpController extends Controller
{
    public function __invoke(
        ConfirmRegistrationTotpRequest $request,
        RegistrationSessionService $registrations,
        InviteRegistrationService $invitations
    ): JsonResponse {
        $data = $request->validated();

        $session = $registrations->resolvePending(
            $data['registration_id'],
            $data['registration_token']
        );

        return response()->json([
            'success' => true,
            'data' => $invitations->confirmTotp(
                $session,
                $data['totp_code'],
                $request
            ),
        ]);
    }
}
