<?php

use App\Http\Controllers\Admin\TradingTools\TradingRoutineController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('trading-tools/trading-routine')
            ->name('trading_routine.')
            ->group(function (): void {
                Route::get('/', [TradingRoutineController::class, 'index'])
                    ->name('index');

                Route::get('/plans/{tradingRoutineDailyPlan}/pdf', [TradingRoutineController::class, 'pdf'])
                    ->name('plans.pdf');
            });
    });
