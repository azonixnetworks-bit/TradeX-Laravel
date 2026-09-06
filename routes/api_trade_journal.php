<?php

use App\Http\Controllers\Api\TradeJournal\TradeJournalController;
use App\Http\Controllers\Api\TradeJournal\TradeJournalReviewController;
use App\Http\Controllers\Api\TradeJournal\TradeJournalScreenshotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'tradex.mobile'])
    ->group(function (): void {
        Route::prefix('trade-journal')
            ->name('trade-journal.')
            ->group(function (): void {
                Route::get('/home', [TradeJournalController::class, 'home'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('home');

                Route::get('/analytics', [TradeJournalController::class, 'analytics'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('analytics');

                Route::get('/reviews/{type}', [TradeJournalReviewController::class, 'show'])
                    ->whereIn('type', ['weekly', 'monthly'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('reviews.show');

                Route::put('/reviews/{type}', [TradeJournalReviewController::class, 'update'])
                    ->whereIn('type', ['weekly', 'monthly'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('reviews.update');

                Route::get('/', [TradeJournalController::class, 'index'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('index');

                Route::post('/', [TradeJournalController::class, 'store'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('store');

                Route::get('/{uuid}', [TradeJournalController::class, 'show'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('show');

                Route::patch('/{uuid}', [TradeJournalController::class, 'update'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('update');

                Route::post('/{uuid}/open', [TradeJournalController::class, 'open'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('open');

                Route::post('/{uuid}/close', [TradeJournalController::class, 'close'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('close');

                Route::post('/{uuid}/cancel', [TradeJournalController::class, 'cancel'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('cancel');

                Route::delete('/{uuid}', [TradeJournalController::class, 'destroy'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('destroy');

                Route::post('/{uuid}/screenshots/{type}', [TradeJournalScreenshotController::class, 'store'])
                    ->whereIn('type', ['before', 'after'])
                    ->middleware('throttle:api-trade-journal-upload')
                    ->name('screenshots.store');

                Route::get('/{uuid}/screenshots/{type}', [TradeJournalScreenshotController::class, 'show'])
                    ->whereIn('type', ['before', 'after'])
                    ->middleware('throttle:api-trade-journal-read')
                    ->name('screenshots.show');

                Route::delete('/{uuid}/screenshots/{type}', [TradeJournalScreenshotController::class, 'destroy'])
                    ->whereIn('type', ['before', 'after'])
                    ->middleware('throttle:api-trade-journal-write')
                    ->name('screenshots.destroy');
            });
    });
