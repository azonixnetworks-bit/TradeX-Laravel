<?php

use App\Http\Controllers\Api\Legal\AcceptLegalPolicyController;
use App\Http\Controllers\Api\Legal\LegalAcceptanceStatusController;
use App\Http\Controllers\Api\Legal\LegalPolicyIndexController;
use App\Http\Controllers\Api\Legal\LegalPolicyShowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-17 — Legal Policy Runtime + Customer Acceptance
|--------------------------------------------------------------------------
|
| Public routes expose only the current published/effective legal policy
| versions so registration/onboarding clients can display policy text.
|
| Acceptance evidence requires the existing authenticated, device-bound
| TradeX mobile session. API-17 records evidence only; it does not silently
| change frozen auth/payment/affiliate gates.
|
*/

Route::middleware(['api'])
    ->prefix('api/v1/legal')
    ->name('api.v1.legal.')
    ->group(function (): void {
        Route::get(
            '/policies',
            LegalPolicyIndexController::class
        )
            ->middleware('throttle:api-legal-public-read')
            ->name('policies.index');

        Route::get(
            '/policies/{policyType}',
            LegalPolicyShowController::class
        )
            ->where('policyType', '[A-Za-z_]+')
            ->middleware('throttle:api-legal-public-read')
            ->name('policies.show');
    });

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix('api/v1/legal')
    ->name('api.v1.legal.')
    ->group(function (): void {
        Route::get(
            '/acceptance-status',
            LegalAcceptanceStatusController::class
        )
            ->middleware('throttle:api-legal-auth-read')
            ->name('acceptance-status');

        Route::post(
            '/policies/{policyVersion}/accept',
            AcceptLegalPolicyController::class
        )
            ->whereUuid('policyVersion')
            ->middleware('throttle:api-legal-accept')
            ->name('policies.accept');
    });
