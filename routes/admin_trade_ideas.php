<?php

use App\Http\Controllers\Admin\TradeIdeas\DailyTradeIdeaController;
use App\Http\Controllers\Admin\TradeIdeas\TradeIdeaController;
use App\Http\Controllers\Admin\TradeIdeas\TradeIdeaDistributionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('trade-ideas/daily')
            ->name('daily_trade_ideas.')
            ->group(function (): void {
                Route::get('/', [DailyTradeIdeaController::class, 'index'])
                    ->name('index');

                Route::get('/create', [DailyTradeIdeaController::class, 'create'])
                    ->name('create');

                Route::post('/', [DailyTradeIdeaController::class, 'store'])
                    ->name('store');

                Route::get(
                    '/{dailyTradeIdea}/chart-image',
                    [DailyTradeIdeaController::class, 'chartImage']
                )
                    ->whereNumber('dailyTradeIdea')
                    ->name('chart_image');

                Route::get('/{dailyTradeIdea}', [DailyTradeIdeaController::class, 'show'])
                    ->whereNumber('dailyTradeIdea')
                    ->name('show');

                Route::get('/{dailyTradeIdea}/edit', [DailyTradeIdeaController::class, 'edit'])
                    ->whereNumber('dailyTradeIdea')
                    ->name('edit');

                Route::patch('/{dailyTradeIdea}', [DailyTradeIdeaController::class, 'update'])
                    ->whereNumber('dailyTradeIdea')
                    ->name('update');

                Route::patch('/{dailyTradeIdea}/publish', [DailyTradeIdeaController::class, 'publish'])
                    ->whereNumber('dailyTradeIdea')
                    ->name('publish');

                Route::patch('/{dailyTradeIdea}/archive', [DailyTradeIdeaController::class, 'archive'])
                    ->whereNumber('dailyTradeIdea')
                    ->name('archive');
            });

        Route::prefix('trade-ideas')
            ->name('trade_ideas.')
            ->group(function (): void {
                Route::get('/', [TradeIdeaController::class, 'index'])
                    ->name('index');

                Route::get('/create', [TradeIdeaController::class, 'create'])
                    ->name('create');

                Route::post('/', [TradeIdeaController::class, 'store'])
                    ->name('store');

                Route::get(
                    '/{tradeIdea}/chart-image',
                    [TradeIdeaController::class, 'chartImage']
                )
                    ->whereNumber('tradeIdea')
                    ->name('chart_image');

                Route::get(
                    '/{tradeIdea}/share-card.svg',
                    [TradeIdeaController::class, 'shareCard']
                )
                    ->whereNumber('tradeIdea')
                    ->name('share_card');

                Route::post(
                    '/{tradeIdea}/distributions',
                    [TradeIdeaController::class, 'distribute']
                )
                    ->whereNumber('tradeIdea')
                    ->name('distributions.store');

                Route::post(
                    '/{tradeIdea}/distributions/{distribution}/retry',
                    [TradeIdeaController::class, 'retryDistribution']
                )
                    ->whereNumber('tradeIdea')
                    ->whereNumber('distribution')
                    ->name('distributions.retry');

                Route::get(
                    '/{tradeIdea}/share-card',
                    [TradeIdeaDistributionController::class, 'preview']
                )
                    ->whereNumber('tradeIdea')
                    ->name('share_card.preview');

                Route::get(
                    '/{tradeIdea}/share-card/render.svg',
                    [TradeIdeaDistributionController::class, 'svg']
                )
                    ->whereNumber('tradeIdea')
                    ->name('share_card.svg');

                Route::get('/{tradeIdea}', [TradeIdeaController::class, 'show'])
                    ->whereNumber('tradeIdea')
                    ->name('show');

                Route::get('/{tradeIdea}/edit', [TradeIdeaController::class, 'edit'])
                    ->whereNumber('tradeIdea')
                    ->name('edit');

                Route::patch('/{tradeIdea}', [TradeIdeaController::class, 'update'])
                    ->whereNumber('tradeIdea')
                    ->name('update');

                Route::patch('/{tradeIdea}/publication', [TradeIdeaController::class, 'publication'])
                    ->whereNumber('tradeIdea')
                    ->name('publication');

                Route::post('/{tradeIdea}/updates', [TradeIdeaController::class, 'addUpdate'])
                    ->whereNumber('tradeIdea')
                    ->name('updates.store');

                Route::patch('/{tradeIdea}/close', [TradeIdeaController::class, 'close'])
                    ->whereNumber('tradeIdea')
                    ->name('close');

                Route::delete('/{tradeIdea}', [TradeIdeaController::class, 'destroy'])
                    ->whereNumber('tradeIdea')
                    ->name('destroy');
            });
    });
