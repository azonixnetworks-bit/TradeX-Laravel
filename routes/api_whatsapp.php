<?php

use App\Http\Controllers\Api\Webhooks\MetaWhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api'])
    ->prefix('api/webhooks/meta/whatsapp')
    ->name('api.webhooks.meta.whatsapp.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                MetaWhatsAppWebhookController::class,
                'verify',
            ]
        )->name('verify');

        Route::post(
            '/',
            [
                MetaWhatsAppWebhookController::class,
                'receive',
            ]
        )->name('receive');
    });
