<?php

namespace App\Http\Controllers\Admin\Screener;

use App\Exceptions\ScreenerProviderException;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Screener\ScreenerEngine;
use App\Services\Screener\ScreenerHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

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

    public function markets(
        Request $request,
        ScreenerEngine $engine
    ): Response {
        $this->viewer();

        $markets = $engine->markets();
        $timeframes = $engine->timeframes();
        $market = strtoupper(trim((string) $request->query('market', 'CRYPTO')));
        $timeframe = strtoupper(trim((string) $request->query('timeframe', 'H1')));

        if (! array_key_exists($market, $markets)) {
            $market = 'CRYPTO';
        }

        if (! array_key_exists($timeframe, $timeframes)) {
            $timeframe = 'H1';
        }

        $results = null;
        $error = null;

        try {
            $results = $engine->screen([
                'market' => $market,
                'timeframe' => $timeframe,
                'sort' => 'symbol',
                'order' => 'asc',
            ]);
        } catch (ScreenerProviderException $exception) {
            $error = [
                'code' => $exception->providerCode,
                'message' => $exception->getMessage(),
            ];
        } catch (InvalidArgumentException $exception) {
            $error = [
                'code' => 'SCREENER_UNAVAILABLE',
                'message' => $exception->getMessage(),
            ];
        }

        return response()->view(
            'admin.screener.markets',
            compact(
                'markets',
                'timeframes',
                'market',
                'timeframe',
                'results',
                'error'
            ),
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

        abort_unless(
            $admin->hasPermission('screener.monitor.view'),
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
