<?php

use App\Http\Controllers\Admin\Customers\CustomerManagementController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/customers')
    ->name('admin.customers.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [
                CustomerManagementController::class,
                'dashboard',
            ]
        )->name('dashboard');

        Route::get(
            '/create',
            [
                CustomerManagementController::class,
                'create',
            ]
        )->name('create');

        Route::post(
            '/',
            [
                CustomerManagementController::class,
                'store',
            ]
        )
            ->middleware('throttle:10,1')
            ->name('store');

        Route::get(
            '/onboarding',
            [
                CustomerManagementController::class,
                'onboarding',
            ]
        )->name('onboarding');
    });
