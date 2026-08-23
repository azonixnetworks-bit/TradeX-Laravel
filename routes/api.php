<?php

use App\Http\Controllers\Api\Auth\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::middleware([
            'auth:sanctum',
            'tradex.mobile',
        ])
            ->prefix('auth')
            ->name('auth.')
            ->group(function (): void {
                Route::get(
                    '/me',
                    MeController::class
                )->name('me');
            });
    });