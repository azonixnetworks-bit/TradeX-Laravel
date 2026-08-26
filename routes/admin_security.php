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
            '/content-protection',
            [ContentProtectionController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('content-protection');
    });
