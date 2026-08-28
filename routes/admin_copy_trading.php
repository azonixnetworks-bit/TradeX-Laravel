<?php

use App\Http\Controllers\Admin\CopyTrading\CopyTradeActivationController;
use App\Http\Controllers\Admin\CopyTrading\CopyTradeBrokerAccountController;
use App\Http\Controllers\Admin\CopyTrading\CopyTradeBrokerController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/copy-trading')
    ->name('admin.copy_trading.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::prefix('brokers')
            ->name('brokers.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [
                        CopyTradeBrokerController::class,
                        'index',
                    ]
                )->name('index');

                Route::get(
                    '/create',
                    [
                        CopyTradeBrokerController::class,
                        'create',
                    ]
                )->name('create');

                Route::post(
                    '/',
                    [
                        CopyTradeBrokerController::class,
                        'store',
                    ]
                )->name('store');

                Route::get(
                    '/{copyTradeBroker}/logo',
                    [
                        CopyTradeBrokerController::class,
                        'logo',
                    ]
                )->name('logo');

                Route::get(
                    '/{copyTradeBroker}/edit',
                    [
                        CopyTradeBrokerController::class,
                        'edit',
                    ]
                )->name('edit');

                Route::patch(
                    '/{copyTradeBroker}',
                    [
                        CopyTradeBrokerController::class,
                        'update',
                    ]
                )->name('update');

                Route::post(
                    '/{copyTradeBroker}/markets',
                    [
                        CopyTradeBrokerController::class,
                        'storeMarket',
                    ]
                )->name('markets.store');

                Route::patch(
                    '/{copyTradeBroker}/markets/{copyTradeBrokerMarket}',
                    [
                        CopyTradeBrokerController::class,
                        'updateMarket',
                    ]
                )->name('markets.update');
            });

        Route::prefix('broker-verifications')
            ->name('verifications.')
            ->group(function (): void {
                Route::get(
                    '/',
                    [
                        CopyTradeBrokerAccountController::class,
                        'verificationIndex',
                    ]
                )->name('index');

                Route::get(
                    '/{copyTradeBrokerAccount}',
                    [
                        CopyTradeBrokerAccountController::class,
                        'show',
                    ]
                )->name('show');

                Route::patch(
                    '/{copyTradeBrokerAccount}/verify',
                    [
                        CopyTradeBrokerAccountController::class,
                        'verify',
                    ]
                )->name('verify');

                Route::patch(
                    '/{copyTradeBrokerAccount}/reject',
                    [
                        CopyTradeBrokerAccountController::class,
                        'reject',
                    ]
                )->name('reject');

                Route::patch(
                    '/{copyTradeBrokerAccount}/request-resubmission',
                    [
                        CopyTradeBrokerAccountController::class,
                        'requestResubmission',
                    ]
                )->name(
                    'request-resubmission'
                );

                Route::patch(
                    '/{copyTradeBrokerAccount}/escalate',
                    [
                        CopyTradeBrokerAccountController::class,
                        'escalate',
                    ]
                )->name('escalate');

                Route::get(
                    '/{copyTradeBrokerAccount}/reviews/{copyTradeBrokerAccountReview}/evidence',
                    [
                        CopyTradeBrokerAccountController::class,
                        'evidence',
                    ]
                )
                    ->whereNumber(
                        'copyTradeBrokerAccountReview'
                    )
                    ->name(
                        'reviews.evidence'
                    );
            });

        Route::get(
            '/accounts',
            [
                CopyTradeBrokerAccountController::class,
                'accountIndex',
            ]
        )->name('accounts.index');

        Route::get(
            '/accounts/{copyTradeBrokerAccount}',
            [
                CopyTradeBrokerAccountController::class,
                'show',
            ]
        )->name('accounts.show');

        Route::get(
            '/accounts/{copyTradeBrokerAccount}/activation',
            [
                CopyTradeActivationController::class,
                'show',
            ]
        )->name('accounts.activation.show');

        Route::post(
            '/accounts/{copyTradeBrokerAccount}/activation/balance',
            [
                CopyTradeActivationController::class,
                'recordBalance',
            ]
        )->name('accounts.activation.balance.store');

        Route::post(
            '/accounts/{copyTradeBrokerAccount}/activation/evaluate',
            [
                CopyTradeActivationController::class,
                'activate',
            ]
        )->name('accounts.activation.evaluate');
    });
