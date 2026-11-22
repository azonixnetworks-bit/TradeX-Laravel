<?php

use App\Http\Controllers\Api\Investments\InvestmentController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
    'throttle:30,1',
])
    ->prefix('api/v1/investments')
    ->name('api.v1.investments.')
    ->group(function (): void {
        Route::get('/', [InvestmentController::class, 'index'])->name('index');
        Route::post('/', [InvestmentController::class, 'store'])->name('store');
        Route::post('/{investment}/deposit', [InvestmentController::class, 'deposit'])
            ->whereUuid('investment')
            ->name('deposit');
        Route::get('/{investment}/agreement', [InvestmentController::class, 'agreement'])
            ->whereUuid('investment')
            ->name('agreement');
    });
