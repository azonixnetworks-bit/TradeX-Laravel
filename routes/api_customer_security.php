<?php

use App\Http\Controllers\Api\Auth\DeviceIndexController;
use App\Http\Controllers\Api\Auth\RemoveDeviceController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('v1/auth/devices')
    ->name('api.v1.auth.devices.')
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
