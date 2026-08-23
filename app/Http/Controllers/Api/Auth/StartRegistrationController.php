<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\StartRegistrationRequest;
use App\Services\Auth\Registration\RegistrationSessionService;
use Illuminate\Http\JsonResponse;

class StartRegistrationController extends Controller
{
    public function __invoke(
        StartRegistrationRequest $request,
        RegistrationSessionService $registrations
    ): JsonResponse {
        return response()->json(
            [
                'success' => true,
                'data' =>
                    $registrations->start(
                        $request->validated(),
                        $request
                    ),
            ],
            201
        );
    }
}
