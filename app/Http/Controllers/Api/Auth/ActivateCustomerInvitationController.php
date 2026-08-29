<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\ActivateCustomerInvitationRequest;
use App\Services\Auth\Registration\InviteRegistrationService;
use Illuminate\Http\JsonResponse;

class ActivateCustomerInvitationController extends Controller
{
    public function __invoke(
        ActivateCustomerInvitationRequest $request,
        InviteRegistrationService $invitations
    ): JsonResponse {
        $data = $request->validated();

        return response()->json(
            [
                'success' => true,
                'data' => $invitations->activate(
                    $data['invitation_id'],
                    $data['token'],
                    $request
                ),
            ],
            201
        );
    }
}
