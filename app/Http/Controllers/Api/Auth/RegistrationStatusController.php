<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegistrationSessionRequest;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class RegistrationStatusController extends Controller
{
    public function __invoke(
        RegistrationSessionRequest $request,
        RegistrationSessionService $registrations
    ): JsonResponse {
        $data = $request->validated();

        $session =
            $registrations->resolveAny(
                $data['registration_id'],
                $data['registration_token']
            );

        return response()->json([
            'success' => true,
            'data' =>
                $registrations->status(
                    $session
                ),
        ]);
    }
}
