<?php

use App\Http\Controllers\Api\Notifications\MarkAllNotificationsReadController;
use App\Http\Controllers\Api\Notifications\MarkNotificationReadController;
use App\Http\Controllers\Api\Notifications\NotificationIndexController;
use App\Http\Controllers\Api\Notifications\NotificationShowController;
use App\Http\Controllers\Api\Notifications\NotificationSummaryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-10 — In-App Notifications Mobile API
|--------------------------------------------------------------------------
|
| This module is an in-app notification center only.
| It does not claim FCM/APNs/WhatsApp/email push delivery.
|
| Security:
| - Laravel API middleware
| - Sanctum authentication
| - TradeX trusted mobile-device/session middleware
| - per-user read/write throttles
| - receipt ownership checks inside NotificationApiService
|
*/

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1/notifications')
    ->name('api.v1.notifications.')
    ->group(function (): void {
        Route::get(
            '/',
            NotificationIndexController::class
        )
            ->middleware(
                'throttle:api-notifications-read'
            )
            ->name('index');

        Route::get(
            '/summary',
            NotificationSummaryController::class
        )
            ->middleware(
                'throttle:api-notifications-read'
            )
            ->name('summary');

        Route::patch(
            '/read-all',
            MarkAllNotificationsReadController::class
        )
            ->middleware(
                'throttle:api-notifications-write'
            )
            ->name('read-all');

        Route::get(
            '/{notification}',
            NotificationShowController::class
        )
            ->whereUuid(
                'notification'
            )
            ->middleware(
                'throttle:api-notifications-read'
            )
            ->name('show');

        Route::patch(
            '/{notification}/read',
            MarkNotificationReadController::class
        )
            ->whereUuid(
                'notification'
            )
            ->middleware(
                'throttle:api-notifications-write'
            )
            ->name('read');
    });
