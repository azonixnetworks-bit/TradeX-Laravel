<?php

use App\Http\Controllers\Admin\MarketClock\MarketClockController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('market-clock')
            ->name('market_clock.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [MarketClockController::class, 'index']
                )->name('index');

                Route::get(
                    '/data',
                    [MarketClockController::class, 'data']
                )->name('data');
            });
    });
