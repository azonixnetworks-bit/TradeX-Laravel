<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Services\Auth\Registration\InviteRegistrationService;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class InvitationLegalPoliciesController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
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
            'data' => $invitations->requiredPolicies($session),
        ]);
    }
}
