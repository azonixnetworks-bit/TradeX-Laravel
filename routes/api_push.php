<?php

use App\Http\Controllers\Api\Push\PushDeviceStatusController;
use App\Http\Controllers\Api\Push\RegisterPushDeviceController;
use App\Http\Controllers\Api\Push\UnregisterPushDeviceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-20 — Push Notification Device Registration + Delivery
|--------------------------------------------------------------------------
|
| Device registration is bound to the existing authenticated trusted mobile
| device session. Delivery fan-out is asynchronous and keeps the existing
| frozen in-app notification contract authoritative for notification content.
|
*/
Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1/push')
    ->group(function (): void {
        Route::get(
            '/device-token',
            PushDeviceStatusController::class
        )
            ->middleware('throttle:api-push-device-read')
            ->name('api.v1.push.device-token.show');

        Route::put(
            '/device-token',
            RegisterPushDeviceController::class
        )
            ->middleware('throttle:api-push-device-mutation')
            ->name('api.v1.push.device-token.register');

        Route::delete(
            '/device-token',
            UnregisterPushDeviceController::class
        )
            ->middleware('throttle:api-push-device-mutation')
            ->name('api.v1.push.device-token.unregister');
    });
