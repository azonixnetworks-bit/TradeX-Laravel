<?php

use App\Http\Controllers\Api\Audit\ActivityAvailabilityController;
use App\Http\Controllers\Api\Audit\IngestUserActivityController;
use Illuminate\Support\Facades\Route;

// Analytics-only endpoint; security and financial auditing remain server-authored.
Route::middleware(['auth:sanctum', 'tradex.mobile', 'throttle:60,1'])
    ->prefix('v1/activity')
    ->name('api.v1.activity.')
    ->group(function (): void {
        Route::get('/config', ActivityAvailabilityController::class)
            ->middleware('throttle:30,1')
            ->name('config');

        Route::post('/events', IngestUserActivityController::class)->name('events.ingest');
    });
