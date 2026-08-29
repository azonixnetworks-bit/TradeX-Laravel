<?php

use App\Http\Controllers\Api\Products\ProductIndexController;
use App\Http\Controllers\Api\Products\ProductShowController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/products')
    ->name('api.v1.products.')
    ->middleware([
        'auth:sanctum',
        'tradex.mobile',
    ])
    ->group(function (): void {
        Route::get(
            '/',
            ProductIndexController::class
        )
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get(
            '/{product}',
            ProductShowController::class
        )
            ->whereUuid('product')
            ->middleware('throttle:60,1')
            ->name('show');
    });
