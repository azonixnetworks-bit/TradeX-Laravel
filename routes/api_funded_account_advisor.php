<?php

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
