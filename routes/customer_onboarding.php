<?php

use App\Http\Controllers\Web\CustomerOnboarding\CustomerActivationPageController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/onboarding/activate',
    CustomerActivationPageController::class
)
    ->middleware('throttle:60,1')
    ->name('customer.onboarding.activate');
