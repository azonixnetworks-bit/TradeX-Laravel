<?php

use App\Http\Controllers\Admin\TradingTools\FundedNoTradeZoneController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/trading-tools/funded-no-trade-zone')
    ->name('admin.funded_no_trade_zone.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [FundedNoTradeZoneController::class, 'index'])
            ->name('index');

        Route::get('/rules/create', [FundedNoTradeZoneController::class, 'create'])
            ->name('rules.create');

        Route::post('/rules', [FundedNoTradeZoneController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('rules.store');

        Route::get('/rules/{fundedNoTradeRule}/edit', [FundedNoTradeZoneController::class, 'edit'])
            ->whereUuid('fundedNoTradeRule')
            ->name('rules.edit');

        Route::patch('/rules/{fundedNoTradeRule}', [FundedNoTradeZoneController::class, 'update'])
            ->whereUuid('fundedNoTradeRule')
            ->middleware('throttle:30,1')
            ->name('rules.update');

        Route::delete('/rules/{fundedNoTradeRule}', [FundedNoTradeZoneController::class, 'destroy'])
            ->whereUuid('fundedNoTradeRule')
            ->middleware('throttle:10,1')
            ->name('rules.destroy');
    });
