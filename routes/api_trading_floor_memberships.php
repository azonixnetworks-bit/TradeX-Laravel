<?php

use App\Http\Controllers\Api\TradingFloor\TradingFloorMembershipHistoryController;
use App\Http\Controllers\Api\TradingFloor\TradingFloorMembershipOverviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/trading-floor')
    ->name('api.v1.trading-floor.')
    ->middleware([
        'auth:sanctum',
        'tradex.mobile',
    ])
    ->group(function (): void {
        Route::get('/membership', TradingFloorMembershipOverviewController::class)
            ->middleware('throttle:60,1')
            ->name('membership.overview');

        Route::get('/membership/history', TradingFloorMembershipHistoryController::class)
            ->middleware('throttle:60,1')
            ->name('membership.history');
    });
