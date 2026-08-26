<?php

use App\Http\Controllers\Admin\Languages\LanguageController;
use App\Http\Controllers\Admin\Languages\TranslationController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin/languages')
    ->name('admin.languages.')
    ->middleware('admin.auth')
    ->group(function (): void {
        Route::get(
            '/',
            [LanguageController::class, 'index']
        )
            ->middleware('throttle:60,1')
            ->name('index');

        Route::get(
            '/keys',
            [LanguageController::class, 'keys']
        )
            ->middleware('throttle:60,1')
            ->name('keys.index');

        Route::get(
            '/keys/create',
            [LanguageController::class, 'createKey']
        )
            ->middleware('throttle:30,1')
            ->name('keys.create');

        Route::post(
            '/keys',
            [LanguageController::class, 'storeKey']
        )
            ->middleware('throttle:20,1')
            ->name('keys.store');

        Route::get(
            '/keys/{translationKey}/edit',
            [LanguageController::class, 'editKey']
        )
            ->whereNumber('translationKey')
            ->middleware('throttle:30,1')
            ->name('keys.edit');

        Route::patch(
            '/keys/{translationKey}',
            [LanguageController::class, 'updateKey']
        )
            ->whereNumber('translationKey')
            ->middleware('throttle:20,1')
            ->name('keys.update');

        Route::patch(
            '/{languageProfile}',
            [LanguageController::class, 'update']
        )
            ->where(
                'languageProfile',
                '[a-z]{2}'
            )
            ->middleware('throttle:15,1')
            ->name('update');

        Route::get(
            '/{languageProfile}/translations',
            [TranslationController::class, 'index']
        )
            ->where(
                'languageProfile',
                '[a-z]{2}'
            )
            ->middleware('throttle:60,1')
            ->name('translations.index');

        Route::get(
            '/{languageProfile}/translations/{translationKey}/edit',
            [TranslationController::class, 'edit']
        )
            ->where(
                'languageProfile',
                '[a-z]{2}'
            )
            ->whereNumber('translationKey')
            ->middleware('throttle:30,1')
            ->name('translations.edit');

        Route::patch(
            '/{languageProfile}/translations/{translationKey}',
            [TranslationController::class, 'update']
        )
            ->where(
                'languageProfile',
                '[a-z]{2}'
            )
            ->whereNumber('translationKey')
            ->middleware('throttle:20,1')
            ->name('translations.update');

        Route::post(
            '/{languageProfile}/translations/{translationKey}/review',
            [TranslationController::class, 'review']
        )
            ->where(
                'languageProfile',
                '[a-z]{2}'
            )
            ->whereNumber('translationKey')
            ->middleware('throttle:10,1')
            ->name('translations.review');
    });
