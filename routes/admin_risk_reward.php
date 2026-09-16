<?php

use App\Http\Controllers\Admin\TradingTools\RiskRewardCalculatorController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('trading-tools/risk-reward')
            ->name('risk_reward.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [RiskRewardCalculatorController::class, 'index']
                )->name('index');

                Route::post(
                    '/calculate',
                    [RiskRewardCalculatorController::class, 'calculate']
                )->name('calculate');
            });
    });
