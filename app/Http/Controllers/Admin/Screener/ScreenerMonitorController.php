<?php

namespace App\Http\Controllers\Admin\Screener;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Screener\ScreenerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ScreenerMonitorController extends Controller
{
    public function index(
        ScreenerHealthService $healthService
    ): Response {
        $this->viewer();

        return response()->view(
            'admin.screener.index',
            [
                'snapshot' => $healthService->snapshot(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function health(
        ScreenerHealthService $healthService
    ): JsonResponse {
        $this->viewer();

        return response()->json(
            [
                'success' => true,
                'data' => $healthService->snapshot(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        // SCREENER-05 is an operational read-only monitor. Reuse the existing
        // dashboard permission rather than widening frozen RBAC grants.
        abort_unless(
            $admin->hasPermission('dashboard.view'),
            403
        );

        return $admin;
    }

    /** @return array<string, string> */
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ];
    }
}
