<?php

use App\Http\Controllers\Api\Affiliate\AffiliateCommissionIndexController;
use App\Http\Controllers\Api\Affiliate\AffiliateMilestoneIndexController;
use App\Http\Controllers\Api\Affiliate\AffiliateOverviewController;
use App\Http\Controllers\Api\Affiliate\AffiliateQualifiedSaleIndexController;
use App\Http\Controllers\Api\Affiliate\AffiliateShareActionController;
use App\Http\Controllers\Api\Affiliate\AffiliateStageIndexController;
use App\Http\Controllers\Api\Affiliate\DirectReferralIndexController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-09 — Affiliate Mobile API
|--------------------------------------------------------------------------
|
| Loaded separately so the frozen routes/api.php contract is not rewritten.
|
| Full affiliate financial/history screens are a PREMIUM entitlement.
| AffiliateApiService enforces that entitlement on every API-09 action.
|
| Security:
| - Laravel API middleware
| - Sanctum authentication
| - TradeX trusted mobile-device/session middleware
| - dedicated authenticated-user rate limits
|
*/

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1/affiliate')
    ->name('api.v1.affiliate.')
    ->group(function (): void {
        Route::get(
            '/overview',
            AffiliateOverviewController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('overview');

        Route::get(
            '/stages',
            AffiliateStageIndexController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('stages.index');

        Route::get(
            '/referrals',
            DirectReferralIndexController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('referrals.index');

        Route::get(
            '/sales',
            AffiliateQualifiedSaleIndexController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('sales.index');

        Route::get(
            '/commissions',
            AffiliateCommissionIndexController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('commissions.index');

        Route::get(
            '/milestones',
            AffiliateMilestoneIndexController::class
        )
            ->middleware(
                'throttle:api-affiliate-read'
            )
            ->name('milestones.index');

        Route::post(
            '/share-action',
            AffiliateShareActionController::class
        )
            ->middleware(
                'throttle:api-affiliate-share'
            )
            ->name('share-action');
    });
