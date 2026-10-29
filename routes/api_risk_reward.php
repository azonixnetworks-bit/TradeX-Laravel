<?php

use App\Http\Controllers\Api\TradingTools\RiskRewardController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/trading-tools/risk-reward')
    ->name('api.v1.risk-reward.')
    ->middleware(['auth:sanctum', 'tradex.mobile'])
    ->group(function (): void {
        Route::get('/catalog', [RiskRewardController::class, 'catalog'])->middleware('throttle:api-risk-reward-read')->name('catalog');
        Route::get('/quote', [RiskRewardController::class, 'quote'])->middleware('throttle:api-risk-reward-read')->name('quote');
        Route::post('/calculate', [RiskRewardController::class, 'calculate'])->middleware('throttle:api-risk-reward-calculate')->name('calculate');
        Route::post('/dividends/calculate', [RiskRewardController::class, 'calculateDividend'])->middleware('throttle:api-risk-reward-calculate')->name('dividends.calculate');
        Route::get('/dividends/{symbol}', [RiskRewardController::class, 'dividendHistory'])->where('symbol', '[A-Za-z0-9._-]+')->middleware('throttle:api-risk-reward-read')->name('dividends.history');
        Route::get('/saved', [RiskRewardController::class, 'saved'])->middleware('throttle:api-risk-reward-read')->name('saved.index');
        Route::post('/saved', [RiskRewardController::class, 'save'])->middleware('throttle:api-risk-reward-write')->name('saved.store');
        Route::delete('/saved/{uuid}', [RiskRewardController::class, 'destroy'])->whereUuid('uuid')->middleware('throttle:api-risk-reward-write')->name('saved.destroy');
    });
