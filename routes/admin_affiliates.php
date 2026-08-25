<?php

use App\Http\Controllers\Admin\Affiliates\AffiliateAnalyticsController;
use App\Http\Controllers\Admin\Affiliates\AffiliateController;
use App\Http\Controllers\Admin\Affiliates\AffiliateStageController;
use App\Http\Controllers\Admin\Affiliates\CommissionController;
use App\Http\Controllers\Admin\Affiliates\ReferralTreeController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/affiliates')
    ->name('admin.affiliates.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [AffiliateController::class, 'index'])
            ->name('index');

        Route::get('/analytics', [AffiliateAnalyticsController::class, 'index'])
            ->name('analytics');

        Route::get('/stages', [AffiliateStageController::class, 'index'])
            ->name('stages.index');

        Route::patch('/stages/{stage}', [AffiliateStageController::class, 'update'])
            ->whereNumber('stage')
            ->middleware('throttle:30,1')
            ->name('stages.update');

        Route::get('/commissions', [CommissionController::class, 'index'])
            ->name('commissions.index');

        Route::get('/{affiliate}/tree', [ReferralTreeController::class, 'show'])
            ->whereNumber('affiliate')
            ->name('tree');

        Route::patch(
            '/{affiliate}/special-commission',
            [AffiliateController::class, 'approveSpecialCommission']
        )
            ->whereNumber('affiliate')
            ->middleware('throttle:20,1')
            ->name('special_commission.update');

        Route::delete(
            '/{affiliate}/special-commission',
            [AffiliateController::class, 'clearSpecialCommission']
        )
            ->whereNumber('affiliate')
            ->middleware('throttle:20,1')
            ->name('special_commission.destroy');

        Route::get('/{affiliate}', [AffiliateController::class, 'show'])
            ->whereNumber('affiliate')
            ->name('show');
    });
