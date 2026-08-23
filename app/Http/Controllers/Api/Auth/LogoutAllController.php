<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\SessionRevocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutAllController extends Controller
{
    public function __invoke(
        Request $request,
        SessionRevocationService $sessions
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $sessions->logoutAll(
            $user,
            $request
        );

        return response()->json([
            'success' => true,
            'message' =>
                'All sessions logged out.',
        ]);
    }
}
