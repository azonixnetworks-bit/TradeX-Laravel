<?php

use App\Http\Controllers\Api\TradingTools\TradingRoutineController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'tradex.mobile'])
    ->group(function (): void {
        Route::prefix('trading-routine')
            ->name('trading-routine.')
            ->group(function (): void {
                Route::get('/', [TradingRoutineController::class, 'index'])
                    ->middleware('throttle:api-trading-routine-read')
                    ->name('index');

                Route::put('/preferences', [TradingRoutineController::class, 'updatePreferences'])
                    ->middleware('throttle:api-trading-routine-write')
                    ->name('preferences.update');

                Route::get('/today', [TradingRoutineController::class, 'today'])
                    ->middleware('throttle:api-trading-routine-read')
                    ->name('today');

                Route::get('/plans/{tradingRoutineDailyPlan}', [TradingRoutineController::class, 'show'])
                    ->whereUuid('tradingRoutineDailyPlan')
                    ->middleware('throttle:api-trading-routine-read')
                    ->name('plans.show');

                Route::get('/plans/{tradingRoutineDailyPlan}/pdf', [TradingRoutineController::class, 'pdf'])
                    ->whereUuid('tradingRoutineDailyPlan')
                    ->middleware('throttle:api-trading-routine-read')
                    ->name('plans.pdf');
            });
    });
