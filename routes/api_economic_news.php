<?php

use App\Http\Controllers\Api\EconomicNews\EconomicNewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/economic-news')
    ->name('api.v1.economic-news.')
    ->middleware([
        'auth:sanctum',
        'tradex.mobile',
        'throttle:api-economic-news-read',
    ])
    ->group(function (): void {
        Route::get('/events', [EconomicNewsController::class, 'index'])
            ->name('events.index');

        Route::get('/high-impact', [EconomicNewsController::class, 'highImpact'])
            ->name('high-impact');

        Route::get('/medium-impact', [EconomicNewsController::class, 'mediumImpact'])
            ->name('medium-impact');

        Route::get('/live', [EconomicNewsController::class, 'live'])
            ->name('live');

        Route::get('/calendar', [EconomicNewsController::class, 'calendar'])
            ->name('calendar');

        Route::get('/events/{economicEvent}', [EconomicNewsController::class, 'show'])
            ->whereUuid('economicEvent')
            ->name('events.show');
    });
