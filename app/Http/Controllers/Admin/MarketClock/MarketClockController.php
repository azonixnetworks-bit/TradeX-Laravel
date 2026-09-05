<?php

namespace App\Http\Controllers\Admin\MarketClock;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\MarketClock\MarketClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class MarketClockController extends Controller
{
    public function index(
        Request $request,
        MarketClockService $marketClock
    ): Response {
        $this->viewer();

        $timezone = $this->timezoneFromRequest(
            $request,
            $marketClock
        );

        return response()->view(
            'admin.market_clock.index',
            [
                'snapshot' => $marketClock->snapshot($timezone),
                'timezone' => $timezone,
                'timezones' => $marketClock->availableTimezones(),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    public function data(
        Request $request,
        MarketClockService $marketClock
    ): JsonResponse {
        $this->viewer();

        $timezone = $this->timezoneFromRequest(
            $request,
            $marketClock
        );

        return response()->json(
            [
                'success' => true,
                'data' => $marketClock->snapshot($timezone),
            ],
            200,
            $this->noStoreHeaders()
        );
    }

    private function timezoneFromRequest(
        Request $request,
        MarketClockService $marketClock
    ): string {
        $timezone = $request->query('timezone');

        if ($timezone === null || trim((string) $timezone) === '') {
            return (string) config(
                'market_clock.default_timezone',
                'UTC'
            );
        }

        try {
            return $marketClock->normalizeTimezone(
                (string) $timezone
            );
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid IANA timezone.');
        }
    }

    private function viewer(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        // Market Clock is a read-only operational dashboard surface. Reusing
        // dashboard.view avoids widening RBAC or changing frozen role grants.
        abort_unless(
            $admin->hasPermission('dashboard.view'),
            403
        );

        return $admin;
    }

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
