<?php

namespace App\Http\Controllers\Api\Audit;

use App\Http\Controllers\Controller;
use App\Services\Audit\ActivityAvailability;
use Illuminate\Http\JsonResponse;

class ActivityAvailabilityController extends Controller
{
    public function __invoke(ActivityAvailability $availability): JsonResponse
    {
        // Authenticated, device-bound handshake; never expose user events.
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => $availability->isReady(),
                'policy_version' => 1,
                'max_batch_size' => (int) config('tradex_activity.max_batch_size', 25),
                'retention_days' => (int) config('tradex_activity.retention_days', 45),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }
}
