<?php

use App\Http\Controllers\Api\Auth\IssueQuickUnlockChallengeController;
use App\Http\Controllers\Api\Auth\VerifyQuickUnlockChallengeController;
use App\Http\Controllers\Api\Security\QuickUnlockForgetController;
use App\Http\Controllers\Api\Security\QuickUnlockShowController;
use App\Http\Controllers\Api\Security\QuickUnlockUpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/v1/auth/quick-unlock')
    ->name('api.v1.auth.quick-unlock.')
    ->group(function (): void {
        Route::post('/challenge', IssueQuickUnlockChallengeController::class)
            ->middleware('throttle:api-quick-unlock-challenge')
            ->name('challenge');

        Route::post('/verify', VerifyQuickUnlockChallengeController::class)
            ->middleware('throttle:api-quick-unlock-verify')
            ->name('verify');
    });

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

        Route::delete(
            '/',
            QuickUnlockForgetController::class
        )
            ->middleware('throttle:api-mobile-security-change')
            ->name('forget');
    });
