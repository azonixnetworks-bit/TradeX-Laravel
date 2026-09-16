<?php

use App\Http\Controllers\Admin\TradingFloor\TradingFloorAccessCredentialController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorClientController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorCommissionInvoiceController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorCommissionPaymentController;
use App\Http\Controllers\Admin\TradingFloor\TradingFloorDashboardController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/trading-floor')
    ->name('admin.trading_floor.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [TradingFloorDashboardController::class, 'index'])
            ->name('index');

        Route::get('/clients', [TradingFloorClientController::class, 'index'])
            ->name('clients.index');

        Route::get('/clients/create', [TradingFloorClientController::class, 'create'])
            ->name('clients.create');

        Route::post('/clients', [TradingFloorClientController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('clients.store');

        Route::get('/clients/{client}', [TradingFloorClientController::class, 'show'])
            ->whereUuid('client')
            ->name('clients.show');

        Route::get('/clients/{client}/edit', [TradingFloorClientController::class, 'edit'])
            ->whereUuid('client')
            ->name('clients.edit');

        Route::patch('/clients/{client}', [TradingFloorClientController::class, 'update'])
            ->whereUuid('client')
            ->middleware('throttle:30,1')
            ->name('clients.update');

        Route::post('/clients/{client}/credentials', [TradingFloorAccessCredentialController::class, 'store'])
            ->whereUuid('client')
            ->middleware('throttle:20,1')
            ->name('clients.credentials.store');

        Route::patch(
            '/clients/{client}/credentials/{credential}/revoke',
            [TradingFloorAccessCredentialController::class, 'revoke']
        )
            ->whereUuid('client')
            ->whereUuid('credential')
            ->middleware('throttle:20,1')
            ->name('clients.credentials.revoke');

        Route::get('/commissions', [TradingFloorCommissionInvoiceController::class, 'index'])
            ->name('commissions.index');

        Route::get(
            '/clients/{client}/commissions/create',
            [TradingFloorCommissionInvoiceController::class, 'create']
        )
            ->whereUuid('client')
            ->name('commissions.create');

        Route::post(
            '/clients/{client}/commissions',
            [TradingFloorCommissionInvoiceController::class, 'store']
        )
            ->whereUuid('client')
            ->middleware('throttle:20,1')
            ->name('commissions.store');

        Route::get('/commissions/{invoice}', [TradingFloorCommissionInvoiceController::class, 'show'])
            ->whereUuid('invoice')
            ->name('commissions.show');

        Route::post(
            '/commissions/{invoice}/payments',
            [TradingFloorCommissionPaymentController::class, 'store']
        )
            ->whereUuid('invoice')
            ->middleware('throttle:20,1')
            ->name('commissions.payments.store');

        Route::patch(
            '/commissions/{invoice}/payments/{payment}/reverse',
            [TradingFloorCommissionPaymentController::class, 'reverse']
        )
            ->whereUuid('invoice')
            ->whereUuid('payment')
            ->middleware('throttle:10,1')
            ->name('commissions.payments.reverse');

        Route::get(
            '/commissions/{invoice}/payments/{payment}/proof',
            [TradingFloorCommissionPaymentController::class, 'proof']
        )
            ->whereUuid('invoice')
            ->whereUuid('payment')
            ->name('commissions.payments.proof');
    });
