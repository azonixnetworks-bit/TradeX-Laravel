<?php

use App\Http\Controllers\Admin\Products\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/products')
    ->name('admin.products.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [
                ProductController::class,
                'index',
            ]
        )->name('index');

        Route::get(
            '/create',
            [
                ProductController::class,
                'create',
            ]
        )->name('create');

        Route::post(
            '/',
            [
                ProductController::class,
                'store',
            ]
        )
            ->middleware('throttle:10,1')
            ->name('store');

        Route::get(
            '/{product}/edit',
            [
                ProductController::class,
                'edit',
            ]
        )
            ->whereUuid('product')
            ->name('edit');

        Route::patch(
            '/{product}',
            [
                ProductController::class,
                'update',
            ]
        )
            ->whereUuid('product')
            ->middleware('throttle:20,1')
            ->name('update');
    });