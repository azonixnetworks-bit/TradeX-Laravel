<?php

use App\Http\Controllers\Admin\Legal\LegalDocumentController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/legal')
    ->name('admin.legal.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [LegalDocumentController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get(
            '/create/{policyType}',
            [LegalDocumentController::class, 'create']
        )
            ->middleware('throttle:30,1')
            ->name('create');

        Route::post(
            '/',
            [LegalDocumentController::class, 'store']
        )
            ->middleware('throttle:15,1')
            ->name('store');

        Route::get(
            '/{legalPolicyVersion}/edit',
            [LegalDocumentController::class, 'edit']
        )
            ->whereNumber(
                'legalPolicyVersion'
            )
            ->middleware('throttle:30,1')
            ->name('edit');

        Route::patch(
            '/{legalPolicyVersion}',
            [LegalDocumentController::class, 'update']
        )
            ->whereNumber(
                'legalPolicyVersion'
            )
            ->middleware('throttle:15,1')
            ->name('update');

        Route::post(
            '/{legalPolicyVersion}/publish',
            [LegalDocumentController::class, 'publish']
        )
            ->whereNumber(
                'legalPolicyVersion'
            )
            ->middleware('throttle:5,1')
            ->name('publish');

        Route::get(
            '/{legalPolicyVersion}',
            [LegalDocumentController::class, 'show']
        )
            ->whereNumber(
                'legalPolicyVersion'
            )
            ->middleware('throttle:60,1')
            ->name('show');
    });
