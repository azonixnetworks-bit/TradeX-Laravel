<?php

use App\Http\Controllers\Admin\OurChannel\ChannelVideoController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('our-channel')
            ->name('our_channel.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [ChannelVideoController::class, 'index']
                )->name('index');

                Route::get(
                    '/create',
                    [ChannelVideoController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [ChannelVideoController::class, 'store']
                )->name('store');

                Route::patch(
                    '/{channelVideo}/publication',
                    [ChannelVideoController::class, 'publication']
                )
                    ->whereNumber('channelVideo')
                    ->name('publication');

                Route::get(
                    '/{channelVideo}/edit',
                    [ChannelVideoController::class, 'edit']
                )
                    ->whereNumber('channelVideo')
                    ->name('edit');

                Route::patch(
                    '/{channelVideo}',
                    [ChannelVideoController::class, 'update']
                )
                    ->whereNumber('channelVideo')
                    ->name('update');

                Route::delete(
                    '/{channelVideo}',
                    [ChannelVideoController::class, 'destroy']
                )
                    ->whereNumber('channelVideo')
                    ->name('destroy');
            });
    });
