<?php

use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\Auth\TotpController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Admin Password Recovery
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/forgot-password',
            [ForgotPasswordController::class, 'show']
        )
            ->middleware('throttle:30,1')
            ->name('password.request');

        Route::post(
            '/forgot-password',
            [ForgotPasswordController::class, 'send']
        )
            ->middleware('throttle:5,1')
            ->name('password.email');

        Route::get(
            '/reset-password/{token}',
            [ResetPasswordController::class, 'show']
        )
            ->middleware('throttle:30,1')
            ->name('password.reset');

        Route::post(
            '/reset-password',
            [ResetPasswordController::class, 'reset']
        )
            ->middleware('throttle:10,1')
            ->name('password.update');

        /*
        |--------------------------------------------------------------------------
        | Short-Lived Public Second-Factor Challenge
        |--------------------------------------------------------------------------
        |
        | These endpoints are not admin.auth routes because the admin guard is
        | intentionally not authenticated until the second factor succeeds.
        | TotpController requires a short-lived pending-login session created
        | only after a valid password.
        |
        */

        Route::get(
            '/totp/challenge',
            [TotpController::class, 'challengePage']
        )
            ->middleware('throttle:30,1')
            ->name('totp.challenge');

        Route::post(
            '/totp/challenge',
            [TotpController::class, 'challenge']
        )
            ->middleware('throttle:10,1')
            ->name('totp.challenge.submit');

        /*
        |--------------------------------------------------------------------------
        | Self-Service Admin TOTP Enrollment
        |--------------------------------------------------------------------------
        |
        | Enrollment requires an authenticated admin session plus current
        | password verification in the Form Requests. There is intentionally
        | no self-service disable/reset endpoint in ADMIN-19.
        |
        */

        Route::prefix('account/totp')
            ->name('account.totp.')
            ->middleware('admin.auth')
            ->group(function (): void {
                Route::get(
                    '/',
                    [TotpController::class, 'settings']
                )
                    ->middleware('throttle:60,1')
                    ->name('settings');

                Route::post(
                    '/setup',
                    [TotpController::class, 'startSetup']
                )
                    ->middleware('throttle:5,1')
                    ->name('setup');

                Route::post(
                    '/confirm',
                    [TotpController::class, 'confirmSetup']
                )
                    ->middleware('throttle:10,1')
                    ->name('confirm');
            });
    });
