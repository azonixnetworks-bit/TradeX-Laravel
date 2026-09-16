<?php

use App\Http\Controllers\Admin\EconomicNews\EconomicNewsDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/economic-news')
    ->name('admin.economic_news.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [EconomicNewsDashboardController::class, 'index'])
            ->name('index');

        Route::get('/high-impact', [EconomicNewsDashboardController::class, 'highImpact'])
            ->name('high_impact');

        Route::get('/medium-impact', [EconomicNewsDashboardController::class, 'mediumImpact'])
            ->name('medium_impact');

        Route::get('/live-updates', [EconomicNewsDashboardController::class, 'live'])
            ->name('live');

        Route::get('/calendar', [EconomicNewsDashboardController::class, 'calendar'])
            ->name('calendar');
    });
