<?php

use App\Http\Controllers\Admin\OurChannel\ChannelConnectionController;
use App\Http\Controllers\Admin\OurChannel\ChannelVideoController;
use App\Http\Controllers\ChannelMediaDeliveryController;
use Illuminate\Support\Facades\Route;

/*
| Signed, short-lived provider fetch URL used by Meta while processing Reels.
| It is intentionally outside /admin because external provider servers cannot
| authenticate to the TradeX admin session. The signature expires automatically.
*/
Route::get(
    '/channel-media/{channelVideo}/source',
    ChannelMediaDeliveryController::class
)
    ->whereNumber('channelVideo')
    ->middleware([
        'signed',
        'throttle:30,1',
    ])
    ->name('channel-media.source');

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
                    '/connections',
                    [ChannelConnectionController::class, 'index']
                )
                    ->middleware('throttle:60,1')
                    ->name('connections.index');

                Route::patch(
                    '/connections/telegram',
                    [ChannelConnectionController::class, 'updateTelegram']
                )
                    ->middleware('throttle:10,1')
                    ->name('connections.telegram.update');

                Route::get(
                    '/create',
                    [ChannelVideoController::class, 'create']
                )->name('create');

                Route::post(
                    '/',
                    [ChannelVideoController::class, 'store']
                )
                    ->middleware('throttle:10,1')
                    ->name('store');

                Route::post(
                    '/{channelVideo}/publications/{publication}/retry',
                    [ChannelVideoController::class, 'retryPublication']
                )
                    ->whereNumber('channelVideo')
                    ->whereNumber('publication')
                    ->middleware('throttle:10,1')
                    ->name('publications.retry');

                Route::patch(
                    '/{channelVideo}/publication',
                    [ChannelVideoController::class, 'publication']
                )
                    ->whereNumber('channelVideo')
                    ->middleware('throttle:10,1')
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
                    ->middleware('throttle:20,1')
                    ->name('update');

                Route::delete(
                    '/{channelVideo}',
                    [ChannelVideoController::class, 'destroy']
                )
                    ->whereNumber('channelVideo')
                    ->middleware('throttle:10,1')
                    ->name('destroy');
            });
    });
