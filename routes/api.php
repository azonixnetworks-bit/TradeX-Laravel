<?php

use App\Http\Controllers\Api\Auth\ConfirmRegistrationTotpController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutAllController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\RegistrationStatusController;
use App\Http\Controllers\Api\Auth\ResendRegistrationEmailOtpController;
use App\Http\Controllers\Api\Auth\ResendRegistrationWhatsAppOtpController;
use App\Http\Controllers\Api\Auth\RevokeCurrentDeviceController;
use App\Http\Controllers\Api\Auth\SetupRegistrationTotpController;
use App\Http\Controllers\Api\Auth\StartRegistrationController;
use App\Http\Controllers\Api\Auth\VerifyRegistrationEmailController;
use App\Http\Controllers\Api\Auth\VerifyRegistrationWhatsAppController;
use App\Http\Controllers\Api\TradeIdeas\UserTradeIdeaShareCardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::post(
                    '/login',
                    LoginController::class
                )
                    ->middleware(
                        'throttle:api-login'
                    )
                    ->name('login');

                Route::post(
                    '/refresh',
                    RefreshController::class
                )
                    ->middleware(
                        'throttle:api-refresh'
                    )
                    ->name('refresh');

                Route::prefix('register')
                    ->name('register.')
                    ->group(function (): void {
                        Route::post(
                            '/start',
                            StartRegistrationController::class
                        )
                            ->middleware(
                                'throttle:api-register-start'
                            )
                            ->name('start');

                        Route::post(
                            '/status',
                            RegistrationStatusController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('status');

                        Route::post(
                            '/email/resend',
                            ResendRegistrationEmailOtpController::class
                        )
                            ->middleware(
                                'throttle:api-register-otp-send'
                            )
                            ->name('email.resend');

                        Route::post(
                            '/email/verify',
                            VerifyRegistrationEmailController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('email.verify');

                        Route::post(
                            '/whatsapp/resend',
                            ResendRegistrationWhatsAppOtpController::class
                        )
                            ->middleware(
                                'throttle:api-register-otp-send'
                            )
                            ->name('whatsapp.resend');

                        Route::post(
                            '/whatsapp/verify',
                            VerifyRegistrationWhatsAppController::class
                        )
                            ->middleware(
                                'throttle:api-register-verify'
                            )
                            ->name('whatsapp.verify');

                        Route::post(
                            '/totp/setup',
                            SetupRegistrationTotpController::class
                        )
                            ->middleware(
                                'throttle:api-register-totp'
                            )
                            ->name('totp.setup');

                        Route::post(
                            '/totp/confirm',
                            ConfirmRegistrationTotpController::class
                        )
                            ->middleware(
                                'throttle:api-register-totp'
                            )
                            ->name('totp.confirm');
                    });
            });

        Route::middleware([
            'auth:sanctum',
            'tradex.mobile',
        ])->group(function (): void {
            Route::prefix('auth')
                ->name('auth.')
                ->group(function (): void {
                    Route::get(
                        '/me',
                        MeController::class
                    )->name('me');

                    Route::post(
                        '/logout',
                        LogoutController::class
                    )->name('logout');

                    Route::post(
                        '/logout-all',
                        LogoutAllController::class
                    )->name('logout-all');

                    Route::post(
                        '/device/revoke',
                        RevokeCurrentDeviceController::class
                    )->name(
                        'device.revoke'
                    );
                });

            Route::get(
                '/trade-ideas/{tradeIdea}/share-card',
                [
                    UserTradeIdeaShareCardController::class,
                    'svg',
                ]
            )
                ->whereNumber(
                    'tradeIdea'
                )
                ->middleware(
                    'throttle:api-share-card'
                )
                ->name(
                    'trade-ideas.share-card'
                );
        });
    });
