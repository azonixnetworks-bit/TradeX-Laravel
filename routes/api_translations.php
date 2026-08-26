<?php

use App\Http\Controllers\Api\Languages\RuntimeLanguageIndexController;
use App\Http\Controllers\Api\Languages\RuntimeTranslationBundleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-18 — Runtime Languages + Reviewed Translation Bundle
|--------------------------------------------------------------------------
|
| Public by design: language selection and translated authentication UI must
| be available before registration/login. Only ADMIN-18 reviewed/current
| translations are eligible; the service falls back to verified English source
| strings for missing, draft, stale or integrity-invalid target values.
|
*/
Route::middleware([
    'api',
    'throttle:api-runtime-translations',
])
    ->prefix('api/v1')
    ->group(function (): void {
        Route::get(
            '/languages',
            RuntimeLanguageIndexController::class
        )->name('api.v1.languages.index');

        Route::get(
            '/translations/{languageCode}',
            RuntimeTranslationBundleController::class
        )
            ->where('languageCode', '[A-Za-z]{2,10}')
            ->name('api.v1.translations.bundle');

        Route::get(
            '/translations/{languageCode}/{group}',
            RuntimeTranslationBundleController::class
        )
            ->where('languageCode', '[A-Za-z]{2,10}')
            ->where('group', '[A-Za-z_]+')
            ->name('api.v1.translations.group');
    });
