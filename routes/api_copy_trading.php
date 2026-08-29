<?php

use App\Http\Controllers\Api\CopyTrading\CopyTradeAccountBillingShowController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBillingIndexController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBillingInvoiceShowController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBootstrapController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBrokerAccountActivationShowController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBrokerAccountResubmitController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBrokerAccountStoreController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBrokerIndexController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeBrokerLogoController;
use App\Http\Controllers\Api\CopyTrading\CopyTradeOpenAccountActionController;
use App\Http\Controllers\Api\CopyTrading\ProfileBrokerAccountIndexController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->middleware([
        'auth:sanctum',
        'tradex.mobile',
    ])
    ->group(function (): void {
        Route::prefix('copy-trade')
            ->name('copy-trade.')
            ->group(function (): void {
                Route::get(
                    '/bootstrap',
                    CopyTradeBootstrapController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name('bootstrap');

                Route::get(
                    '/brokers',
                    CopyTradeBrokerIndexController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name('brokers.index');

                Route::get(
                    '/brokers/{copyTradeBroker}/logo',
                    CopyTradeBrokerLogoController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name('brokers.logo');

                Route::post(
                    '/broker-markets/{copyTradeBrokerMarket}/open-account-action',
                    CopyTradeOpenAccountActionController::class
                )
                    ->middleware(
                        'throttle:api-profile-sensitive'
                    )
                    ->name(
                        'broker-markets.open-account-action'
                    );

                Route::post(
                    '/broker-accounts',
                    CopyTradeBrokerAccountStoreController::class
                )
                    ->middleware(
                        'throttle:api-profile-sensitive'
                    )
                    ->name(
                        'broker-accounts.store'
                    );

                Route::post(
                    '/broker-accounts/{copyTradeBrokerAccount}/resubmit',
                    CopyTradeBrokerAccountResubmitController::class
                )
                    ->middleware(
                        'throttle:api-profile-sensitive'
                    )
                    ->name(
                        'broker-accounts.resubmit'
                    );

                Route::get(
                    '/broker-accounts/{copyTradeBrokerAccount}/activation',
                    CopyTradeBrokerAccountActivationShowController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name(
                        'broker-accounts.activation.show'
                    );

                Route::get(
                    '/billing',
                    CopyTradeBillingIndexController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name('billing.index');

                Route::get(
                    '/broker-accounts/{copyTradeBrokerAccount}/billing',
                    CopyTradeAccountBillingShowController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name(
                        'broker-accounts.billing.show'
                    );

                Route::get(
                    '/billing/invoices/{copyTradeBillingInvoice}',
                    CopyTradeBillingInvoiceShowController::class
                )
                    ->middleware(
                        'throttle:api-profile-read'
                    )
                    ->name(
                        'billing.invoices.show'
                    );
            });

        Route::get(
            '/profile/broker-accounts',
            ProfileBrokerAccountIndexController::class
        )
            ->middleware(
                'throttle:api-profile-read'
            )
            ->name(
                'profile.broker-accounts.index'
            );
    });
