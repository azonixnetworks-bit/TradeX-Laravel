<?php

use App\Http\Controllers\Admin\TradingTools\FundedAccountAdvisorController;
use App\Http\Controllers\Admin\TradingTools\FundedAccountPartnerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('trading-tools/funded-account-advisor')
            ->name('funded_advisor.')
            ->group(function (): void {
                Route::get('/', [FundedAccountAdvisorController::class, 'index'])
                    ->name('index');
                Route::post('/reports', [FundedAccountAdvisorController::class, 'generate'])
                    ->name('reports.generate');
                Route::get('/reports/{fundedAdvisorReport}', [FundedAccountAdvisorController::class, 'show'])
                    ->name('reports.show');
                Route::get('/reports/{fundedAdvisorReport}/pdf', [FundedAccountAdvisorController::class, 'pdf'])
                    ->name('reports.pdf');

                Route::prefix('partners')
                    ->name('partners.')
                    ->group(function (): void {
                        Route::get('/', [FundedAccountPartnerController::class, 'index'])
                            ->name('index');
                        Route::get('/create', [FundedAccountPartnerController::class, 'create'])
                            ->name('create');
                        Route::post('/', [FundedAccountPartnerController::class, 'store'])
                            ->name('store');
                        Route::get('/{fundedAccountPartner}/edit', [FundedAccountPartnerController::class, 'edit'])
                            ->name('edit');
                        Route::put('/{fundedAccountPartner}', [FundedAccountPartnerController::class, 'update'])
                            ->name('update');
                        Route::delete('/{fundedAccountPartner}', [FundedAccountPartnerController::class, 'destroy'])
                            ->name('destroy');
                        Route::get('/{fundedAccountPartner}/logo', [FundedAccountPartnerController::class, 'logo'])
                            ->name('logo');
                    });
            });
    });
