<?php

use App\Http\Controllers\Api\TradingTools\CustomerFundedAccountController;
use App\Http\Controllers\Api\TradingTools\FundedAccountAdvisorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['auth:sanctum', 'tradex.mobile'])
    ->group(function (): void {
        Route::prefix('funded-account-advisor')
            ->name('funded-advisor.')
            ->group(function (): void {
                Route::get('/', [FundedAccountAdvisorController::class, 'index'])
                    ->middleware('throttle:api-funded-advisor-read')
                    ->name('index');

                Route::get('/account', [CustomerFundedAccountController::class, 'show'])
                    ->middleware('throttle:api-funded-advisor-read')
                    ->name('account.show');

                Route::put('/account', [CustomerFundedAccountController::class, 'update'])
                    ->middleware('throttle:api-funded-advisor-write')
                    ->name('account.update');

                Route::delete('/account', [CustomerFundedAccountController::class, 'destroy'])
                    ->middleware('throttle:api-funded-advisor-write')
                    ->name('account.destroy');

                Route::get('/no-trade-zones', [CustomerFundedAccountController::class, 'zones'])
                    ->middleware('throttle:api-funded-advisor-read')
                    ->name('no-trade-zones.index');

                Route::post('/reports', [FundedAccountAdvisorController::class, 'generate'])
                    ->middleware('throttle:api-funded-advisor-write')
                    ->name('reports.generate');

                Route::get('/reports/{fundedAdvisorReport}', [FundedAccountAdvisorController::class, 'show'])
                    ->middleware('throttle:api-funded-advisor-read')
                    ->name('reports.show');

                Route::get('/reports/{fundedAdvisorReport}/pdf', [FundedAccountAdvisorController::class, 'pdf'])
                    ->middleware('throttle:api-funded-advisor-read')
                    ->name('reports.pdf');
            });
    });
