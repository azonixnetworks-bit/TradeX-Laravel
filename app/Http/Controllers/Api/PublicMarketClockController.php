<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MarketClock\MarketClockService;
use Illuminate\Http\JsonResponse;

class PublicMarketClockController extends Controller
{
    public function __invoke(MarketClockService $marketClock): JsonResponse
    {
        $snapshot = $marketClock->snapshot('UTC');
        $publicKeys = ['sydney', 'tokyo', 'london', 'new_york'];
        $sessions = collect($snapshot['sessions'])
            ->whereIn('key', $publicKeys)
            ->sortBy(fn (array $session): int => array_search($session['key'], $publicKeys, true))
            ->values()
            ->map(fn (array $session): array => [
                'key' => $session['key'],
                'name' => $session['name'],
                'region' => $session['region'],
                'timezone' => $session['timezone'],
                'status' => $session['status'],
                'local_time' => $session['local_time'],
                'local_date' => $session['local_date'],
                'open_local' => $session['configured_open_local'],
                'close_local' => $session['configured_close_local'],
                'close_at_utc' => $session['close_at_utc'],
                'next_open_at_utc' => $session['next_open_at_utc'],
                'dial_segments' => $session['dial_segments'],
            ]);

        return response()->json([
            'data' => [
                'server_time_utc' => $snapshot['server_time_utc'],
                'market_status' => $snapshot['market_status'],
                'open_now' => $sessions->where('status', 'open')->count(),
                'sessions' => $sessions,
            ],
        ]);
    }
}
