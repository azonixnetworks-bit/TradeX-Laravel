<?php

use App\Http\Controllers\Admin\Screener\ScreenerMonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('screener')
            ->name('screener.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [ScreenerMonitorController::class, 'index']
                )->name('index');

                Route::get(
                    '/health',
                    [ScreenerMonitorController::class, 'health']
                )->name('health');

                Route::get(
                    '/markets',
                    [ScreenerMonitorController::class, 'markets']
                )->name('markets');

                Route::get(
                    '/bubbles',
                    [ScreenerMonitorController::class, 'bubbles']
                )->name('bubbles');

                Route::get(
                    '/markets/chart',
                    [ScreenerMonitorController::class, 'chart']
                )->name('markets.chart');

                Route::get(
                    '/markets/analysis',
                    [ScreenerMonitorController::class, 'marketAnalysis']
                )->name('markets.analysis');

                Route::get(
                    '/markets/watchlist',
                    [ScreenerMonitorController::class, 'watchlist']
                )->name('markets.watchlist');

                Route::get(
                    '/markets/catalog/warm',
                    [ScreenerMonitorController::class, 'warmCryptoCatalog']
                )->name('markets.catalog.warm');

                Route::post(
                    '/markets/watchlist',
                    [ScreenerMonitorController::class, 'storeWatchlist']
                )->name('markets.watchlist.store');

                Route::delete(
                    '/markets/watchlist/{symbol}',
                    [ScreenerMonitorController::class, 'destroyWatchlist']
                )->where('symbol', '[A-Za-z0-9]{3,30}')
                    ->name('markets.watchlist.destroy');
            });
    });
