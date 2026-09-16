<?php

use App\Http\Controllers\Admin\TradingFloor\TradingFloorDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/trading-floor')
    ->name('admin.trading_floor.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [TradingFloorDashboardController::class, 'index'])
            ->name('index');
    });
