<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __invoke(
        LoginRequest $request,
        LoginService $loginService
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $loginService->login(
                $request->validated(),
                $request
            ),
        ]);
    }
}
