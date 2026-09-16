<?php

use App\Http\Controllers\Admin\EconomicNews\EconomicEventController;
use App\Http\Controllers\Admin\EconomicNews\EconomicEventOverrideController;
use App\Http\Controllers\Admin\EconomicNews\EconomicEventUpdateController;
use App\Http\Controllers\Admin\EconomicNews\EconomicNewsDashboardController;
use App\Http\Controllers\Admin\EconomicNews\EconomicNewsSourceController;
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

        Route::get('/events/create', [EconomicEventController::class, 'create'])
            ->name('events.create');

        Route::post('/events', [EconomicEventController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('events.store');

        Route::get('/events/{economicEvent}/edit', [EconomicEventController::class, 'edit'])
            ->whereUuid('economicEvent')
            ->name('events.edit');

        Route::patch('/events/{economicEvent}', [EconomicEventController::class, 'update'])
            ->whereUuid('economicEvent')
            ->middleware('throttle:30,1')
            ->name('events.update');

        Route::delete('/events/{economicEvent}', [EconomicEventController::class, 'destroy'])
            ->whereUuid('economicEvent')
            ->middleware('throttle:10,1')
            ->name('events.destroy');

        Route::post('/events/{economicEvent}/updates', [EconomicEventUpdateController::class, 'store'])
            ->whereUuid('economicEvent')
            ->middleware('throttle:30,1')
            ->name('events.updates.store');

        Route::post('/events/{economicEvent}/overrides', [EconomicEventOverrideController::class, 'store'])
            ->whereUuid('economicEvent')
            ->middleware('throttle:20,1')
            ->name('events.overrides.store');

        Route::patch(
            '/events/{economicEvent}/overrides/{economicEventOverride}/revoke',
            [EconomicEventOverrideController::class, 'revoke']
        )
            ->whereUuid('economicEvent')
            ->whereUuid('economicEventOverride')
            ->middleware('throttle:20,1')
            ->name('events.overrides.revoke');

        Route::get('/sources', [EconomicNewsSourceController::class, 'index'])
            ->name('sources.index');

        Route::post('/sources', [EconomicNewsSourceController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('sources.store');

        Route::patch('/sources/{economicNewsSource}', [EconomicNewsSourceController::class, 'update'])
            ->whereUuid('economicNewsSource')
            ->middleware('throttle:30,1')
            ->name('sources.update');

        Route::delete('/sources/{economicNewsSource}', [EconomicNewsSourceController::class, 'destroy'])
            ->whereUuid('economicNewsSource')
            ->middleware('throttle:10,1')
            ->name('sources.destroy');
    });
