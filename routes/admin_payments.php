<?php

use App\Http\Controllers\Admin\Payments\BankDepositController;
use App\Http\Controllers\Admin\Payments\NowPaymentsController;
use App\Http\Controllers\Admin\Payments\PayPalController;
use App\Http\Controllers\Admin\Payments\PaymentAccountController;
use App\Http\Controllers\Admin\Payments\PaymentController;
use App\Http\Controllers\Admin\Payments\UsdtDepositController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/payments')
    ->name('admin.payments.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [PaymentController::class, 'index'])
            ->name('index');

        Route::get('/accounts', [PaymentAccountController::class, 'index'])
            ->name('accounts.index');

        Route::get('/paypal', [PayPalController::class, 'index'])
            ->name('paypal.index');

        Route::get('/nowpayments', [NowPaymentsController::class, 'index'])
            ->name('nowpayments.index');

        Route::get('/bank-deposits', [BankDepositController::class, 'index'])
            ->name('bank.index');

        Route::get('/bank-deposits/{bankDeposit}/proof', [BankDepositController::class, 'proof'])
            ->whereNumber('bankDeposit')
            ->middleware('throttle:60,1')
            ->name('bank.proof');

        Route::patch('/bank-deposits/{bankDeposit}/approve', [BankDepositController::class, 'approve'])
            ->whereNumber('bankDeposit')
            ->middleware('throttle:20,1')
            ->name('bank.approve');

        Route::patch('/bank-deposits/{bankDeposit}/reject', [BankDepositController::class, 'reject'])
            ->whereNumber('bankDeposit')
            ->middleware('throttle:20,1')
            ->name('bank.reject');

        Route::get('/usdt-deposits', [UsdtDepositController::class, 'index'])
            ->name('usdt.index');

        Route::patch('/usdt-deposits/{usdtDeposit}/approve', [UsdtDepositController::class, 'approve'])
            ->whereNumber('usdtDeposit')
            ->middleware('throttle:20,1')
            ->name('usdt.approve');

        Route::patch('/usdt-deposits/{usdtDeposit}/reject', [UsdtDepositController::class, 'reject'])
            ->whereNumber('usdtDeposit')
            ->middleware('throttle:20,1')
            ->name('usdt.reject');

        Route::get('/{paymentOrder}', [PaymentController::class, 'show'])
            ->whereNumber('paymentOrder')
            ->name('show');
    });
