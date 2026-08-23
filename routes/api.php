<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutAllController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RefreshController;
use App\Http\Controllers\Api\Auth\RevokeCurrentDeviceController;
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
