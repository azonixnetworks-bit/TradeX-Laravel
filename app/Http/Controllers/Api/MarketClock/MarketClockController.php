<?php

namespace App\Http\Controllers\Api\MarketClock;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketClock\MarketClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MarketClockController extends Controller
{
    public function __invoke(
        Request $request,
        MarketClockService $marketClock
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user instanceof User,
            401,
            'Unauthenticated.'
        );

        $timezone = $request->query('timezone');

        try {
            $timezone = $marketClock->normalizeTimezone(
                $timezone === null
                    ? (string) config('market_clock.default_timezone', 'UTC')
                    : (string) $timezone
            );
        } catch (InvalidArgumentException) {
            abort(422, 'Invalid IANA timezone.');
        }

        return response()->json([
            'success' => true,
            'data' => $marketClock->snapshot($timezone),
        ]);
    }
}
