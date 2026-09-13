<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Auth\CustomerSecurityCenterService;
use App\Services\Auth\CustomerSecurityEventReviewService;
use App\Services\Auth\CustomerSecurityHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityActivityController extends Controller
{
    public function center(
        Request $request,
        CustomerSecurityCenterService $security
    ): JsonResponse {
        $user = $request->user();
        $currentDevice = $request->attributes->get('tradex_user_device');

        abort_unless(
            $user instanceof User
            && $currentDevice instanceof UserDevice,
            401,
            'Unauthenticated.'
        );

        return response()->json([
            'success' => true,
            'data' => $security->center(
                $user,
                $currentDevice
            ),
        ]);
    }

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

    public function review(
        Request $request,
        int $eventId,
        CustomerSecurityEventReviewService $security
    ): JsonResponse {
        $user = $request->user();
        $currentDevice = $request->attributes->get('tradex_user_device');

        abort_unless(
            $user instanceof User
            && $currentDevice instanceof UserDevice,
            401,
            'Unauthenticated.'
        );

        $validated = $request->validate([
            'response' => [
                'required',
                'string',
                Rule::in([
                    CustomerSecurityEventReviewService::RESPONSE_YES,
                    CustomerSecurityEventReviewService::RESPONSE_NO,
                ]),
            ],
        ]);

        $result = $security->respond(
            $user,
            $currentDevice,
            $eventId,
            (string) $validated['response'],
            $request
        );

        return response()->json([
            'success' => true,
            'data' => $result,
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
