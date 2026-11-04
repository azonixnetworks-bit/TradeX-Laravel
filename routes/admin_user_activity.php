<?php

use App\Http\Controllers\Admin\Activity\UserActivityAnalyticsController;
use Illuminate\Support\Facades\Route;

// Separate from the frozen ADMIN-12 audit log routes. Controller additionally
// enforces Super Admin role; no existing audit or user routes are changed.
Route::prefix('admin/analytics/user-activity')
    ->name('admin.activity.')
    ->middleware(['admin.auth', 'throttle:60,1'])
    ->group(function (): void {
        Route::get('/', [UserActivityAnalyticsController::class, 'index'])
            ->name('index');
        Route::get('/export', [UserActivityAnalyticsController::class, 'export'])
            ->middleware('throttle:10,1')
            ->name('export');
    });
