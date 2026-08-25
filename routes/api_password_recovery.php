<?php

use App\Http\Controllers\Api\Auth\PasswordRecovery\ResendPasswordRecoveryEmailController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\ResendPasswordRecoveryResetLinkController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\ResendPasswordRecoveryWhatsAppController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\ResetRecoveredPasswordController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\StartPasswordRecoveryController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\VerifyPasswordRecoveryEmailController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\VerifyPasswordRecoveryTotpController;
use App\Http\Controllers\Api\Auth\PasswordRecovery\VerifyPasswordRecoveryWhatsAppController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/v1/auth/password-recovery')
    ->name('api.v1.auth.password-recovery.')
    ->group(function (): void {
        Route::post(
            '/start',
            StartPasswordRecoveryController::class
        )
            ->middleware('throttle:api-password-recovery-start')
            ->name('start');

        Route::post(
            '/email/verify',
            VerifyPasswordRecoveryEmailController::class
        )
            ->middleware('throttle:api-password-recovery-verify')
            ->name('email.verify');

        Route::post(
            '/email/resend',
            ResendPasswordRecoveryEmailController::class
        )
            ->middleware('throttle:api-password-recovery-resend')
            ->name('email.resend');

        Route::post(
            '/whatsapp/verify',
            VerifyPasswordRecoveryWhatsAppController::class
        )
            ->middleware('throttle:api-password-recovery-verify')
            ->name('whatsapp.verify');

        Route::post(
            '/whatsapp/resend',
            ResendPasswordRecoveryWhatsAppController::class
        )
            ->middleware('throttle:api-password-recovery-resend')
            ->name('whatsapp.resend');

        Route::post(
            '/totp/verify',
            VerifyPasswordRecoveryTotpController::class
        )
            ->middleware('throttle:api-password-recovery-verify')
            ->name('totp.verify');

        Route::post(
            '/reset-link/resend',
            ResendPasswordRecoveryResetLinkController::class
        )
            ->middleware('throttle:api-password-recovery-resend')
            ->name('reset-link.resend');

        Route::post(
            '/reset',
            ResetRecoveredPasswordController::class
        )
            ->middleware('throttle:api-password-recovery-reset')
            ->name('reset');
    });
