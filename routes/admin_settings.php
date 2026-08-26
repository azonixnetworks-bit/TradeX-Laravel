<?php

use App\Http\Controllers\Admin\Settings\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/settings')
    ->name('admin.settings.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [SettingController::class, 'home']
        )
            ->middleware('throttle:60,1')
            ->name('home');

        Route::get(
            '/general',
            [SettingController::class, 'general']
        )
            ->middleware('throttle:60,1')
            ->name('general');

        Route::patch(
            '/general',
            [SettingController::class, 'updateGeneral']
        )
            ->middleware('throttle:10,1')
            ->name('general.update');

        Route::get(
            '/affiliate',
            [SettingController::class, 'affiliate']
        )
            ->middleware('throttle:60,1')
            ->name('affiliate');

        Route::get(
            '/payments',
            [SettingController::class, 'payments']
        )
            ->middleware('throttle:60,1')
            ->name('payments');

        Route::get(
            '/security',
            [SettingController::class, 'security']
        )
            ->middleware('throttle:60,1')
            ->name('security');

        Route::get(
            '/video',
            [SettingController::class, 'video']
        )
            ->middleware('throttle:60,1')
            ->name('video');
    });
