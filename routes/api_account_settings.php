<?php

use App\Http\Controllers\Api\Profile\Settings\AccountSecurityController;
use App\Http\Controllers\Api\Profile\Settings\AccountSettingsController;
use App\Http\Controllers\Api\Profile\Settings\RevokeTrustedDeviceController;
use App\Http\Controllers\Api\Profile\Settings\TrustedDeviceIndexController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1/profile/settings')
    ->name('api.v1.profile.settings.')
    ->group(function (): void {
        Route::get('/', AccountSettingsController::class)
            ->middleware('throttle:api-account-settings-read')
            ->name('show');

        Route::get('/security', AccountSecurityController::class)
            ->middleware('throttle:api-account-settings-read')
            ->name('security');

        Route::get('/devices', TrustedDeviceIndexController::class)
            ->middleware('throttle:api-account-settings-read')
            ->name('devices.index');

        Route::patch('/devices/{device}/revoke', RevokeTrustedDeviceController::class)
            ->whereUuid('device')
            ->middleware('throttle:api-account-settings-sensitive')
            ->name('devices.revoke');
    });
