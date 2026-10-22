<?php

use App\Http\Controllers\Api\Screener\ScreenerController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([
        'auth:sanctum',
        'tradex.mobile',
    ])
    ->group(function (): void {
        Route::prefix('screener')
            ->name('screener.')
            ->middleware(
                'throttle:api-screener-read'
            )
            ->group(function (): void {
                Route::get(
                    '/meta',
                    [
                        ScreenerController::class,
                        'meta',
                    ]
                )->name('meta');

                Route::get(
                    '/health',
                    [
                        ScreenerController::class,
                        'health',
                    ]
                )->name('health');

                Route::get(
                    '/symbols',
                    [
                        ScreenerController::class,
                        'symbols',
                    ]
                )->name('symbols');

                Route::get(
                    '/results',
                    [
                        ScreenerController::class,
                        'results',
                    ]
                )->name('results');

                Route::get(
                    '/bubbles',
                    [
                        ScreenerController::class,
                        'bubbles',
                    ]
                )->name('bubbles');

                Route::get(
                    '/symbols/{symbol}',
                    [
                        ScreenerController::class,
                        'show',
                    ]
                )
                    ->where(
                        'symbol',
                        '[A-Za-z0-9._-]+'
                    )
                    ->name('show');
            });
    });
