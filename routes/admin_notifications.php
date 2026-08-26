<?php

use App\Http\Controllers\Admin\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/notifications')
    ->name('admin.notifications.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [NotificationController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get(
            '/create',
            [NotificationController::class, 'create']
        )
            ->middleware('throttle:30,1')
            ->name('create');

        Route::post(
            '/',
            [NotificationController::class, 'store']
        )
            ->middleware('throttle:20,1')
            ->name('store');

        Route::get(
            '/{notificationMessage}/edit',
            [NotificationController::class, 'edit']
        )
            ->whereNumber(
                'notificationMessage'
            )
            ->middleware('throttle:30,1')
            ->name('edit');

        Route::patch(
            '/{notificationMessage}',
            [NotificationController::class, 'update']
        )
            ->whereNumber(
                'notificationMessage'
            )
            ->middleware('throttle:20,1')
            ->name('update');

        Route::post(
            '/{notificationMessage}/send',
            [NotificationController::class, 'send']
        )
            ->whereNumber(
                'notificationMessage'
            )
            ->middleware('throttle:5,1')
            ->name('send');

        Route::get(
            '/{notificationMessage}',
            [NotificationController::class, 'show']
        )
            ->whereNumber(
                'notificationMessage'
            )
            ->middleware('throttle:60,1')
            ->name('show');
    });
