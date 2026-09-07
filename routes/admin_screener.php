<?php

use App\Http\Controllers\Admin\Screener\ScreenerLiveController;
use App\Http\Controllers\Admin\Screener\ScreenerMonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('screener')
            ->name('screener.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [ScreenerMonitorController::class, 'index']
                )->name('index');

                Route::get(
                    '/health',
                    [ScreenerMonitorController::class, 'health']
                )->name('health');

                Route::get(
                    '/live',
                    [ScreenerLiveController::class, 'index']
                )->name('live');

                Route::get(
                    '/live/data',
                    [ScreenerLiveController::class, 'data']
                )->name('live_data');
            });
    });
