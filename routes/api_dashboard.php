<?php

use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\MarketClock\MarketClockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-08 — Mobile Dashboard
|--------------------------------------------------------------------------
|
| This route file is loaded by DashboardServiceProvider so API-08 can be
| added without rewriting the already-frozen routes/api.php contract.
|
| Security boundary:
| - Laravel API middleware
| - Sanctum authentication
| - TradeX trusted mobile-device/session middleware
| - dedicated authenticated-user rate limit
|
*/

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get(
            '/dashboard',
            DashboardController::class
        )
            ->middleware(
                'throttle:api-dashboard-read'
            )
            ->name('dashboard.show');

        Route::get(
            '/market-clock',
            MarketClockController::class
        )
            ->middleware(
                'throttle:api-dashboard-read'
            )
            ->name('market-clock.show');
    });
