<?php

use App\Http\Controllers\Api\Bootstrap\MobileBootstrapController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-19 — Mobile Bootstrap + Safe Runtime Settings
|--------------------------------------------------------------------------
|
| Public by design so the mobile client can obtain safe startup metadata
| before registration/login. The service has an explicit API-level allowlist
| and never exposes arbitrary system_settings rows or secret configuration.
|
*/
Route::middleware([
    'api',
    'throttle:api-mobile-bootstrap',
])
    ->prefix('api/v1')
    ->group(function (): void {
        Route::get(
            '/bootstrap',
            MobileBootstrapController::class
        )->name('api.v1.bootstrap.show');
    });
