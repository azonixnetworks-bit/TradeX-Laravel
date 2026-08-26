<?php

use App\Http\Controllers\Admin\Kyc\KycController;
use App\Http\Controllers\Admin\Kyc\KycReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/kyc')
    ->name('admin.kyc.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [KycController::class, 'index'])
            ->name('index');

        Route::get('/{kycSubmission}', [KycController::class, 'show'])
            ->whereNumber('kycSubmission')
            ->name('show');

        Route::get('/{kycSubmission}/identity', [KycController::class, 'identity'])
            ->whereNumber('kycSubmission')
            ->middleware('throttle:30,1')
            ->name('identity');

        Route::get('/{kycSubmission}/documents/{kycDocument}', [KycController::class, 'document'])
            ->whereNumber('kycSubmission')
            ->whereNumber('kycDocument')
            ->middleware('throttle:60,1')
            ->name('document');

        Route::patch('/{kycSubmission}/approve', [KycReviewController::class, 'approve'])
            ->whereNumber('kycSubmission')
            ->middleware('throttle:20,1')
            ->name('approve');

        Route::patch('/{kycSubmission}/reject', [KycReviewController::class, 'reject'])
            ->whereNumber('kycSubmission')
            ->middleware('throttle:20,1')
            ->name('reject');

        Route::patch('/{kycSubmission}/request-resubmission', [KycReviewController::class, 'requestResubmission'])
            ->whereNumber('kycSubmission')
            ->middleware('throttle:20,1')
            ->name('request-resubmission');
    });
