<?php

use App\Http\Controllers\Admin\Investments\InvestmentActivationController;
use App\Http\Controllers\Admin\Investments\InvestmentAgreementController;
use App\Http\Controllers\Admin\Investments\InvestmentController;
use App\Http\Controllers\Admin\Investments\InvestmentDashboardController;
use App\Http\Controllers\Admin\Investments\InvestmentDepositController;
use App\Http\Controllers\Admin\Investments\InvestmentRiskController;
use App\Http\Controllers\Admin\Investments\InvestmentWalletController;
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
            '/deposit-verification',
            [InvestmentDepositController::class, 'index']
        )->name('deposits.index');

        Route::get(
            '/agreements',
            [InvestmentAgreementController::class, 'index']
        )->name('agreements.index');

        Route::get(
            '/activation-queue',
            [InvestmentActivationController::class, 'index']
        )->name('activation.index');

        Route::post(
            '/{investment}/deposits',
            [InvestmentDepositController::class, 'store']
        )
            ->whereNumber('investment')
            ->name('deposits.store');

        Route::post(
            '/{investment}/deposits/{transaction}/verify',
            [InvestmentDepositController::class, 'verify']
        )
            ->whereNumber('investment')
            ->whereNumber('transaction')
            ->name('deposits.verify');

        Route::post(
            '/{investment}/deposits/{transaction}/reject',
            [InvestmentDepositController::class, 'reject']
        )
            ->whereNumber('investment')
            ->whereNumber('transaction')
            ->name('deposits.reject');

        Route::post(
            '/{investment}/risk-acceptance',
            [InvestmentRiskController::class, 'store']
        )
            ->whereNumber('investment')
            ->name('risk.store');

        Route::post(
            '/{investment}/payout-wallet/verify',
            [InvestmentWalletController::class, 'verify']
        )
            ->whereNumber('investment')
            ->name('wallet.verify');

        Route::post(
            '/{investment}/agreements/generate',
            [InvestmentAgreementController::class, 'generate']
        )
            ->whereNumber('investment')
            ->name('agreements.generate');

        Route::post(
            '/{investment}/agreements/{agreement}/signed',
            [InvestmentAgreementController::class, 'uploadSigned']
        )
            ->whereNumber('investment')
            ->whereNumber('agreement')
            ->name('agreements.signed.upload');

        Route::post(
            '/{investment}/agreements/{agreement}/verify',
            [InvestmentAgreementController::class, 'verify']
        )
            ->whereNumber('investment')
            ->whereNumber('agreement')
            ->name('agreements.verify');

        Route::get(
            '/{investment}/agreements/{agreement}/generated',
            [InvestmentAgreementController::class, 'generated']
        )
            ->whereNumber('investment')
            ->whereNumber('agreement')
            ->name('agreements.generated.download');

        Route::get(
            '/{investment}/agreements/{agreement}/signed',
            [InvestmentAgreementController::class, 'signed']
        )
            ->whereNumber('investment')
            ->whereNumber('agreement')
            ->name('agreements.signed.download');

        Route::post(
            '/{investment}/activate',
            [InvestmentActivationController::class, 'activate']
        )
            ->whereNumber('investment')
            ->name('activate');

        Route::get(
            '/{investment}',
            [InvestmentController::class, 'show']
        )
            ->whereNumber('investment')
            ->name('show');
    });
