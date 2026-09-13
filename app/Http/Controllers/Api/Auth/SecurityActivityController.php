<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\CustomerSecurityHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityActivityController extends Controller
{
    public function history(
        Request $request,
        CustomerSecurityHistoryService $security
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $security
                    ->history(
                        $user,
                        $this->limit($request, 30)
                    )
                    ->values(),
            ],
        ]);
    }

    public function notifications(
        Request $request,
        CustomerSecurityHistoryService $security
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $security
                    ->notifications(
                        $user,
                        $this->limit($request, 20)
                    )
                    ->values(),
                'read_tracking' => false,
            ],
        ]);
    }

    private function limit(Request $request, int $default): int
    {
        $value = $request->query('limit');

        if ($value === null || $value === '') {
            return $default;
        }

        if (! is_numeric($value)) {
            return $default;
        }

        return max(1, min(50, (int) $value));
    }
}
