<?php

use App\Http\Controllers\Admin\Security\ContentProtectionController;
use App\Http\Controllers\Admin\Security\SecurityEventController;
use App\Http\Controllers\Admin\Security\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/security')
    ->name('admin.security.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [SecurityEventController::class, 'home']
        )
            ->name('home');

        Route::get(
            '/events',
            [SecurityEventController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('events');

        Route::get(
            '/sessions',
            [SessionController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('sessions');

        Route::get(
            '/admin-sessions/{adminSession}',
            [SessionController::class, 'showAdminSession']
        )
            ->whereNumber('adminSession')
            ->middleware('throttle:60,1')
            ->name('admin-sessions.show');

        Route::get(
            '/content-protection',
            [ContentProtectionController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('content-protection');
    });

/*
|--------------------------------------------------------------------------
| Admin Session Mutation Boundary
|--------------------------------------------------------------------------
|
| ADMIN-11 keeps /admin/security/* strictly read-only. Session revocation
| actions live on a separate admin control surface while preserving the
| existing route names used by the Security UI.
|
*/
Route::prefix('admin')
    ->name('admin.security.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::patch(
            '/admin-sessions/{adminSession}/revoke',
            [SessionController::class, 'revokeAdminSession']
        )
            ->whereNumber('adminSession')
            ->middleware('throttle:20,1')
            ->name('admin-sessions.revoke');

        Route::patch(
            '/admin-users/{adminUser}/sessions/revoke-all',
            [SessionController::class, 'revokeAllAdminSessions']
        )
            ->whereNumber('adminUser')
            ->middleware('throttle:10,1')
            ->name('admin-sessions.revoke-all');
    });
