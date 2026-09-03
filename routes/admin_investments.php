<?php

use App\Http\Controllers\Admin\Investments\InvestmentController;
use App\Http\Controllers\Admin\Investments\InvestmentDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/investments')
    ->name('admin.investments.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            InvestmentDashboardController::class
        )->name('dashboard');

        Route::get(
            '/all',
            [InvestmentController::class, 'index']
        )->name('index');

        Route::get(
            '/create',
            [InvestmentController::class, 'create']
        )->name('create');

        Route::post(
            '/',
            [InvestmentController::class, 'store']
        )->name('store');

        Route::get(
            '/{investment}',
            [InvestmentController::class, 'show']
        )
            ->whereNumber('investment')
            ->name('show');
    });
