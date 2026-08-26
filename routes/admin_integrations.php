<?php

use App\Http\Controllers\Admin\Integrations\IntegrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/integrations')
    ->name('admin.integrations.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [IntegrationController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get(
            '/{providerCode}',
            [IntegrationController::class, 'show']
        )
            ->where(
                'providerCode',
                'PAYPAL|STRIPE|NOWPAYMENTS|LOCAL_BANK'
            )
            ->middleware('throttle:60,1')
            ->name('show');

        Route::patch(
            '/{providerCode}',
            [IntegrationController::class, 'update']
        )
            ->where(
                'providerCode',
                'PAYPAL|STRIPE|NOWPAYMENTS|LOCAL_BANK'
            )
            ->middleware('throttle:10,1')
            ->name('update');
    });
