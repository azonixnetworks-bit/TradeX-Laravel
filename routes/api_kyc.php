<?php

use App\Http\Controllers\Api\Kyc\KycRequirementsController;
use App\Http\Controllers\Api\Kyc\KycStatusController;
use App\Http\Controllers\Api\Kyc\KycSubmissionShowController;
use App\Http\Controllers\Api\Kyc\KycSubmissionStoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-13 — KYC & Compliance Mobile API
|--------------------------------------------------------------------------
|
| KYC remains separate from the Education Premium payment entitlement.
| These endpoints require an authenticated TradeX mobile API session.
|
| Raw identity data, identity hashes, reviewer notes and private storage
| locations must never be exposed through these routes.
|
*/

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix(
        'api/v1/kyc'
    )
    ->name(
        'api.v1.kyc.'
    )
    ->group(function (): void {
        Route::get(
            '/status',
            KycStatusController::class
        )
            ->middleware(
                'throttle:api-kyc-read'
            )
            ->name(
                'status'
            );

        Route::get(
            '/requirements',
            KycRequirementsController::class
        )
            ->middleware(
                'throttle:api-kyc-read'
            )
            ->name(
                'requirements'
            );

        Route::get(
            '/submissions/{kycSubmission}',
            KycSubmissionShowController::class
        )
            ->whereUuid(
                'kycSubmission'
            )
            ->middleware(
                'throttle:api-kyc-read'
            )
            ->name(
                'submissions.show'
            );

        Route::post(
            '/submissions',
            KycSubmissionStoreController::class
        )
            ->middleware(
                'throttle:api-kyc-submit'
            )
            ->name(
                'submissions.store'
            );
    });
