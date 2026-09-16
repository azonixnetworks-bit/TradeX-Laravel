<?php

use App\Http\Controllers\Api\TradingFloor\TradingFloorAccessScanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/trading-floor/access')
    ->name('api.v1.trading-floor.access.')
    ->middleware('trading.floor.device')
    ->group(function (): void {
        Route::post('/scan', TradingFloorAccessScanController::class)
            ->middleware('throttle:120,1')
            ->name('scan');
    });
