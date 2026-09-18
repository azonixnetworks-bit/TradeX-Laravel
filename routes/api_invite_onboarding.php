<?php

use App\Http\Controllers\Api\Auth\AcceptInvitationLegalPoliciesController;
use App\Http\Controllers\Api\Auth\ActivateCustomerInvitationController;
use App\Http\Controllers\Api\Auth\BootstrapInvitationRecoveryCodesController;
use App\Http\Controllers\Api\Auth\CompleteInvitationOnboardingController;
use App\Http\Controllers\Api\Auth\ConfirmInvitationTotpController;
use App\Http\Controllers\Api\Auth\InvitationLegalPoliciesController;
use App\Http\Controllers\Api\Auth\RegenerateMfaRecoveryCodesController;
use App\Http\Controllers\Api\Auth\SetInvitationPasswordController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth/invite')
    ->name('api.v1.auth.invite.')
    ->group(function (): void {
        Route::post(
            '/activate',
            ActivateCustomerInvitationController::class
        )
            ->middleware('throttle:api-register-start')
            ->name('activate');

        Route::post(
            '/password',
            SetInvitationPasswordController::class
        )
            ->middleware('throttle:api-register-verify')
            ->name('password');

        Route::post(
            '/totp/confirm',
            ConfirmInvitationTotpController::class
        )
            ->middleware('throttle:api-register-totp')
            ->name('totp.confirm');

        Route::post(
            '/legal/policies',
            InvitationLegalPoliciesController::class
        )
            ->middleware('throttle:api-register-verify')
            ->name('legal.policies');

        Route::post(
            '/legal/accept',
            AcceptInvitationLegalPoliciesController::class
        )
            ->middleware('throttle:api-register-verify')
            ->name('legal.accept');

        Route::post(
            '/complete',
            CompleteInvitationOnboardingController::class
        )
            ->middleware('throttle:api-register-verify')
            ->name('complete');

        Route::post(
            '/recovery-codes',
            BootstrapInvitationRecoveryCodesController::class
        )
            ->middleware('throttle:api-register-verify')
            ->name('recovery-codes.bootstrap');
    });

Route::middleware([
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('v1/auth')
    ->name('api.v1.auth.')
    ->group(function (): void {
        Route::post(
            '/recovery-codes/regenerate',
            RegenerateMfaRecoveryCodesController::class
        )
            ->middleware('throttle:api-register-totp')
            ->name('recovery-codes.regenerate');
    });

/* ADMIN-30 device-facing Trading Floor access routes. */
require __DIR__.'/api_trading_floor.php';
