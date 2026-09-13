<?php

use App\Http\Controllers\Api\Auth\DeviceIndexController;
use App\Http\Controllers\Api\Auth\RemoveDeviceController;
use App\Http\Controllers\Api\Auth\SecurityActivityController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('v1/auth')
    ->name('api.v1.auth.')
    ->group(function (): void {
        Route::prefix('devices')
            ->name('devices.')
            ->group(function (): void {
                Route::get(
                    '/',
                    DeviceIndexController::class
                )
                    ->middleware('throttle:60,1')
                    ->name('index');

                Route::delete(
                    '/{deviceUuid}',
                    RemoveDeviceController::class
                )
                    ->whereUuid('deviceUuid')
                    ->middleware('throttle:10,1')
                    ->name('destroy');
            });

        Route::prefix('security')
            ->name('security.')
            ->group(function (): void {
                Route::get(
                    '/history',
                    [SecurityActivityController::class, 'history']
                )
                    ->middleware('throttle:60,1')
                    ->name('history');

                Route::get(
                    '/notifications',
                    [SecurityActivityController::class, 'notifications']
                )
                    ->middleware('throttle:60,1')
                    ->name('notifications');
            });
    });
