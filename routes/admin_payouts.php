<?php

use App\Http\Controllers\Admin\Payouts\PayoutController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/payouts')
    ->name('admin.payouts.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get('/', [PayoutController::class, 'index'])
            ->name('index');

        Route::get('/{payoutRequest}', [PayoutController::class, 'show'])
            ->whereNumber('payoutRequest')
            ->name('show');

        Route::get('/{payoutRequest}/destination', [PayoutController::class, 'destination'])
            ->whereNumber('payoutRequest')
            ->middleware('throttle:30,1')
            ->name('destination');

        Route::patch('/{payoutRequest}/approve', [PayoutController::class, 'approve'])
            ->whereNumber('payoutRequest')
            ->middleware('throttle:20,1')
            ->name('approve');

        Route::patch('/{payoutRequest}/reject', [PayoutController::class, 'reject'])
            ->whereNumber('payoutRequest')
            ->middleware('throttle:20,1')
            ->name('reject');

        Route::patch('/{payoutRequest}/mark-paid', [PayoutController::class, 'markPaid'])
            ->whereNumber('payoutRequest')
            ->middleware('throttle:20,1')
            ->name('mark-paid');
    });
