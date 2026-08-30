<?php

use App\Http\Controllers\Admin\Finance\DailySalesController;
use App\Http\Controllers\Admin\Finance\FinanceDashboardController;
use App\Http\Controllers\Admin\Finance\MonthlySalesController;
use App\Http\Controllers\Admin\Finance\ReconciliationController;
use App\Http\Controllers\Admin\Finance\SalesReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/finance')
    ->name('admin.finance.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [FinanceDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/sales-report', [SalesReportController::class, 'index'])
            ->name('sales-report');

        Route::get('/sales-report/export', [SalesReportController::class, 'export'])
            ->middleware('throttle:20,1')
            ->name('sales-report.export');

        Route::get('/daily-sales', [DailySalesController::class, 'index'])
            ->name('daily');

        Route::get('/daily-sales/export', [DailySalesController::class, 'export'])
            ->middleware('throttle:30,1')
            ->name('daily.export');

        Route::get('/monthly-sales', [MonthlySalesController::class, 'index'])
            ->name('monthly');

        Route::get('/monthly-sales/export', [MonthlySalesController::class, 'export'])
            ->middleware('throttle:30,1')
            ->name('monthly.export');

        Route::get('/reconciliation', [ReconciliationController::class, 'index'])
            ->middleware('throttle:30,1')
            ->name('reconciliation');
    });
