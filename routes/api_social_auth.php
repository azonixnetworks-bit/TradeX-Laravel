<?php

use App\Http\Controllers\Api\Auth\Social\ConfirmSocialOnboardingController;
use App\Http\Controllers\Api\Auth\Social\IssueSocialChallengeController;
use App\Http\Controllers\Api\Auth\Social\LinkSocialAccountController;
use App\Http\Controllers\Api\Auth\Social\SocialLoginController;
use App\Http\Controllers\Api\Auth\Social\StartSocialOnboardingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/v1/auth/social')
    ->name('api.v1.auth.social.')
    ->group(function (): void {
        Route::post('/{provider}/challenge', IssueSocialChallengeController::class)
            ->where('provider', 'google|apple')
            ->middleware('throttle:api-social-challenge')
            ->name('challenge');

        Route::post('/{provider}/login', SocialLoginController::class)
            ->where('provider', 'google|facebook|apple')
            ->middleware('throttle:api-social-login')
            ->name('login');

        Route::post('/{provider}/onboard', StartSocialOnboardingController::class)
            ->where('provider', 'google|facebook|apple')
            ->middleware('throttle:api-social-onboard')
            ->name('onboard');

        Route::post('/{provider}/onboard/confirm', ConfirmSocialOnboardingController::class)
            ->where('provider', 'google|facebook|apple')
            ->middleware('throttle:api-social-confirm')
            ->name('onboard.confirm');

        Route::post('/{provider}/link', LinkSocialAccountController::class)
            ->where('provider', 'google|facebook|apple')
            ->middleware(['auth:sanctum', 'tradex.mobile', 'throttle:api-social-link'])
            ->name('link');
    });
