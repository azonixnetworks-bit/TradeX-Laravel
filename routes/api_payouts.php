<?php

use App\Http\Controllers\Api\Payouts\PayoutHistoryController;
use App\Http\Controllers\Api\Payouts\PayoutMethodIndexController;
use App\Http\Controllers\Api\Payouts\PayoutShowController;
use App\Http\Controllers\Api\Payouts\PayoutStoreController;
use App\Http\Controllers\Api\Payouts\PayoutSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api','auth:sanctum','tradex.mobile'])->prefix('api/v1/payouts')->name('api.v1.payouts.')->group(function():void{
    Route::get('/summary',PayoutSummaryController::class)->middleware('throttle:api-payouts-read')->name('summary');
    Route::get('/methods',PayoutMethodIndexController::class)->middleware('throttle:api-payouts-read')->name('methods');
    Route::get('/history',PayoutHistoryController::class)->middleware('throttle:api-payouts-read')->name('history');
    Route::post('/',PayoutStoreController::class)->middleware('throttle:api-payouts-create')->name('store');
    Route::get('/{payoutRequest}',PayoutShowController::class)->whereUuid('payoutRequest')->middleware('throttle:api-payouts-read')->name('show');
});
