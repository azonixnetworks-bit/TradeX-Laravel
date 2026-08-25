<?php

use App\Http\Controllers\Api\Security\QuickUnlockShowController;
use App\Http\Controllers\Api\Security\QuickUnlockUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix(
        'api/v1/security/quick-unlock'
    )
    ->name(
        'api.v1.security.quick-unlock.'
    )
    ->group(function (): void {
        Route::get(
            '/',
            QuickUnlockShowController::class
        )
            ->middleware(
                'throttle:api-mobile-security-read'
            )
            ->name('show');

        Route::patch(
            '/',
            QuickUnlockUpdateController::class
        )
            ->middleware(
                'throttle:api-mobile-security-change'
            )
            ->name('update');
    });
