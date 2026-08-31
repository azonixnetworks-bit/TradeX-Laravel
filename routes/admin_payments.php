<?php

use App\Http\Controllers\Admin\Payments\BankDepositController;
use App\Http\Controllers\Admin\Payments\CryptoDepositController;
use App\Http\Controllers\Admin\Payments\NowPaymentsController;
use App\Http\Controllers\Admin\Payments\PayPalController;
use App\Http\Controllers\Admin\Payments\PaymentAccountController;
use App\Http\Controllers\Admin\Payments\PaymentController;
use App\Http\Controllers\Admin\Payments\PaymentReceivingAccountController;
use App\Http\Controllers\Admin\Payments\StripeController;
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

        Route::post(
            '/accounts',
            [PaymentReceivingAccountController::class, 'store']
        )
            ->middleware('throttle:20,1')
            ->name('accounts.receiving.store');

        Route::patch(
            '/accounts/{paymentReceivingAccount}',
            [PaymentReceivingAccountController::class, 'update']
        )
            ->whereNumber('paymentReceivingAccount')
            ->middleware('throttle:20,1')
            ->name('accounts.receiving.update');

        Route::get(
            '/accounts/{paymentReceivingAccount}/qr',
            [PaymentReceivingAccountController::class, 'qr']
        )
            ->whereNumber('paymentReceivingAccount')
            ->middleware('throttle:60,1')
            ->name('accounts.receiving.qr');

        Route::get('/paypal', [PayPalController::class, 'index'])
            ->name('paypal.index');

        Route::get('/stripe', [StripeController::class, 'index'])
            ->name('stripe.index');

        Route::get('/nowpayments', [NowPaymentsController::class, 'index'])
            ->name('nowpayments.index');

        Route::get('/bank-deposits', [BankDepositController::class, 'index'])
            ->name('bank.index');

        Route::get(
            '/bank-deposits/{bankDeposit}/proof',
            [BankDepositController::class, 'proof']
        )
            ->whereNumber('bankDeposit')
            ->middleware('throttle:60,1')
            ->name('bank.proof');

        Route::patch(
            '/bank-deposits/{bankDeposit}/approve',
            [BankDepositController::class, 'approve']
        )
            ->whereNumber('bankDeposit')
            ->middleware('throttle:20,1')
            ->name('bank.approve');

        Route::patch(
            '/bank-deposits/{bankDeposit}/reject',
            [BankDepositController::class, 'reject']
        )
            ->whereNumber('bankDeposit')
            ->middleware('throttle:20,1')
            ->name('bank.reject');

        Route::get('/crypto-deposits', [CryptoDepositController::class, 'index'])
            ->name('crypto.index');

        Route::get(
            '/crypto-deposits/{cryptoDeposit}/proof',
            [CryptoDepositController::class, 'proof']
        )
            ->whereNumber('cryptoDeposit')
            ->middleware('throttle:60,1')
            ->name('crypto.proof');

        Route::patch(
            '/crypto-deposits/{cryptoDeposit}/approve',
            [CryptoDepositController::class, 'approve']
        )
            ->whereNumber('cryptoDeposit')
            ->middleware('throttle:20,1')
            ->name('crypto.approve');

        Route::patch(
            '/crypto-deposits/{cryptoDeposit}/reject',
            [CryptoDepositController::class, 'reject']
        )
            ->whereNumber('cryptoDeposit')
            ->middleware('throttle:20,1')
            ->name('crypto.reject');

        Route::get('/usdt-deposits', [UsdtDepositController::class, 'index'])
            ->name('usdt.index');

        Route::get(
            '/usdt-deposits/{usdtDeposit}/proof',
            [UsdtDepositController::class, 'proof']
        )
            ->whereNumber('usdtDeposit')
            ->middleware('throttle:60,1')
            ->name('usdt.proof');

        Route::patch(
            '/usdt-deposits/{usdtDeposit}/approve',
            [UsdtDepositController::class, 'approve']
        )
            ->whereNumber('usdtDeposit')
            ->middleware('throttle:20,1')
            ->name('usdt.approve');

        Route::patch(
            '/usdt-deposits/{usdtDeposit}/reject',
            [UsdtDepositController::class, 'reject']
        )
            ->whereNumber('usdtDeposit')
            ->middleware('throttle:20,1')
            ->name('usdt.reject');

        Route::get('/{paymentOrder}', [PaymentController::class, 'show'])
            ->whereNumber('paymentOrder')
            ->name('show');
    });
