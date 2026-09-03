<?php

use App\Http\Controllers\Admin\Investments\InvestmentActivationController;
use App\Http\Controllers\Admin\Investments\InvestmentAgreementController;
use App\Http\Controllers\Admin\Investments\InvestmentController;
use App\Http\Controllers\Admin\Investments\InvestmentDashboardController;
use App\Http\Controllers\Admin\Investments\InvestmentDepositController;
use App\Http\Controllers\Admin\Investments\InvestmentRiskController;
use App\Http\Controllers\Admin\Investments\InvestmentWalletController;
use App\Http\Controllers\Admin\Investments\InvestmentAdjustmentController; // ADMIN-24-PHASE3-V3-ROUTES
use App\Http\Controllers\Admin\Investments\InvestmentCostController;
use App\Http\Controllers\Admin\Investments\InvestmentEvidenceController;
use App\Http\Controllers\Admin\Investments\InvestmentProfitPayoutController;
use App\Http\Controllers\Admin\Investments\InvestmentProfitPeriodController;
use App\Http\Controllers\Admin\Investments\InvestmentTopUpController;
use App\Http\Controllers\Admin\Investments\InvestmentTradeController;
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


        // ADMIN-24 Phase 3 V3 — realized-profit engine, evidence, top-ups and payouts.
        Route::get('/profit-periods', [InvestmentProfitPeriodController::class, 'index'])
            ->name('profit_periods.index');
        Route::get('/{investment}/profit-periods/{period}', [InvestmentProfitPeriodController::class, 'show'])
            ->whereNumber('investment')->whereNumber('period')->name('profit_periods.show');
        Route::post('/{investment}/profit-periods/{period}/prepare', [InvestmentProfitPeriodController::class, 'prepare'])
            ->whereNumber('investment')->whereNumber('period')->name('profit_periods.prepare');
        Route::post('/{investment}/profit-periods/{period}/approve', [InvestmentProfitPeriodController::class, 'approve'])
            ->whereNumber('investment')->whereNumber('period')->name('profit_periods.approve');

        Route::post('/{investment}/trades', [InvestmentTradeController::class, 'store'])
            ->whereNumber('investment')->name('trades.store');
        Route::post('/{investment}/trades/{trade}/verify', [InvestmentTradeController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('trade')->name('trades.verify');
        Route::post('/{investment}/trades/{trade}/reject', [InvestmentTradeController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('trade')->name('trades.reject');

        Route::post('/{investment}/trades/{trade}/evidence', [InvestmentEvidenceController::class, 'tradeStore'])
            ->whereNumber('investment')->whereNumber('trade')->name('evidence.trade.store');
        Route::post('/{investment}/profit-periods/{period}/evidence', [InvestmentEvidenceController::class, 'periodStore'])
            ->whereNumber('investment')->whereNumber('period')->name('evidence.period.store');
        Route::post('/{investment}/costs/{cost}/evidence', [InvestmentEvidenceController::class, 'costStore'])
            ->whereNumber('investment')->whereNumber('cost')->name('evidence.cost.store');
        Route::post('/{investment}/evidence/{evidence}/verify', [InvestmentEvidenceController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('evidence')->name('evidence.verify');
        Route::post('/{investment}/evidence/{evidence}/reject', [InvestmentEvidenceController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('evidence')->name('evidence.reject');
        Route::get('/{investment}/evidence/{evidence}', [InvestmentEvidenceController::class, 'download'])
            ->whereNumber('investment')->whereNumber('evidence')->name('evidence.download');

        Route::post('/{investment}/costs', [InvestmentCostController::class, 'store'])
            ->whereNumber('investment')->name('costs.store');
        Route::post('/{investment}/costs/{cost}/verify', [InvestmentCostController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('cost')->name('costs.verify');
        Route::post('/{investment}/costs/{cost}/reject', [InvestmentCostController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('cost')->name('costs.reject');

        Route::post('/{investment}/financial-adjustments', [InvestmentAdjustmentController::class, 'store'])
            ->whereNumber('investment')->name('adjustments.store');
        Route::post('/{investment}/financial-adjustments/{adjustment}/verify', [InvestmentAdjustmentController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('adjustment')->name('adjustments.verify');
        Route::post('/{investment}/financial-adjustments/{adjustment}/reject', [InvestmentAdjustmentController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('adjustment')->name('adjustments.reject');

        Route::post('/{investment}/top-ups', [InvestmentTopUpController::class, 'store'])
            ->whereNumber('investment')->name('topups.store');
        Route::post('/{investment}/top-ups/{transaction}/verify', [InvestmentTopUpController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('transaction')->name('topups.verify');
        Route::post('/{investment}/top-ups/{transaction}/reject', [InvestmentTopUpController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('transaction')->name('topups.reject');

        Route::post('/{investment}/profit-periods/{period}/payout', [InvestmentProfitPayoutController::class, 'store'])
            ->whereNumber('investment')->whereNumber('period')->name('profit_payouts.store');
        Route::post('/{investment}/profit-periods/{period}/payout/{transaction}/verify', [InvestmentProfitPayoutController::class, 'verify'])
            ->whereNumber('investment')->whereNumber('period')->whereNumber('transaction')->name('profit_payouts.verify');
        Route::post('/{investment}/profit-periods/{period}/payout/{transaction}/reject', [InvestmentProfitPayoutController::class, 'reject'])
            ->whereNumber('investment')->whereNumber('period')->whereNumber('transaction')->name('profit_payouts.reject');

        Route::get(
            '/{investment}',
            [InvestmentController::class, 'show']
        )
            ->whereNumber('investment')
            ->name('show');
    });
