<?php

use App\Http\Controllers\Api\Payments\CapturePayPalOrderController;
use App\Http\Controllers\Api\Payments\CreateNowPaymentsInvoiceController;
use App\Http\Controllers\Api\Payments\CreatePayPalOrderController;
use App\Http\Controllers\Api\Payments\CreateStripeCheckoutController;
use App\Http\Controllers\Api\Payments\NowPaymentsWebhookController;
use App\Http\Controllers\Api\Payments\PaymentActivationStatusController;
use App\Http\Controllers\Api\Payments\PaymentHistoryController;
use App\Http\Controllers\Api\Payments\PaymentMethodIndexController;
use App\Http\Controllers\Api\Payments\PaymentOrderShowController;
use App\Http\Controllers\Api\Payments\PayPalWebhookController;
use App\Http\Controllers\Api\Payments\StripeWebhookController;
use App\Http\Controllers\Api\Payments\SubmitBankDepositController;
use App\Http\Controllers\Api\Payments\SubmitUsdtDepositController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API-12 — Payments & Billing Mobile API
|--------------------------------------------------------------------------
|
| Provider webhooks are provider-to-server and therefore do not use a user
| Sanctum/mobile session. Their authenticity is verified cryptographically
| before any payment state is changed.
|
| All user-facing payment endpoints remain bound to the trusted TradeX mobile
| session contract.
|
*/

Route::middleware([
    'api',
])
    ->prefix(
        'api/v1/payments'
    )
    ->name(
        'api.v1.payments.'
    )
    ->group(function (): void {
        Route::post(
            '/paypal/webhook',
            PayPalWebhookController::class
        )
            ->middleware(
                'throttle:api-payments-webhook'
            )
            ->name(
                'paypal.webhook'
            );

        Route::post(
            '/stripe/webhook',
            StripeWebhookController::class
        )
            ->middleware(
                'throttle:api-payments-webhook'
            )
            ->name(
                'stripe.webhook'
            );

        Route::post(
            '/nowpayments/webhook',
            NowPaymentsWebhookController::class
        )
            ->middleware(
                'throttle:api-payments-webhook'
            )
            ->name(
                'nowpayments.webhook'
            );
    });

Route::middleware([
    'api',
    'auth:sanctum',
    'tradex.mobile',
])
    ->prefix(
        'api/v1/payments'
    )
    ->name(
        'api.v1.payments.'
    )
    ->group(function (): void {
        Route::get(
            '/methods',
            PaymentMethodIndexController::class
        )
            ->middleware(
                'throttle:api-payments-read'
            )
            ->name(
                'methods.index'
            );

        Route::get(
            '/activation-status',
            PaymentActivationStatusController::class
        )
            ->middleware(
                'throttle:api-payments-read'
            )
            ->name(
                'activation-status'
            );

        Route::get(
            '/history',
            PaymentHistoryController::class
        )
            ->middleware(
                'throttle:api-payments-read'
            )
            ->name(
                'history'
            );

        Route::get(
            '/orders/{paymentOrder}',
            PaymentOrderShowController::class
        )
            ->whereUuid(
                'paymentOrder'
            )
            ->middleware(
                'throttle:api-payments-read'
            )
            ->name(
                'orders.show'
            );

        Route::post(
            '/paypal/orders',
            CreatePayPalOrderController::class
        )
            ->middleware(
                'throttle:api-payments-create'
            )
            ->name(
                'paypal.orders.create'
            );

        Route::post(
            '/paypal/orders/{paymentOrder}/capture',
            CapturePayPalOrderController::class
        )
            ->whereUuid(
                'paymentOrder'
            )
            ->middleware(
                'throttle:api-payments-capture'
            )
            ->name(
                'paypal.orders.capture'
            );

        Route::post(
            '/stripe/checkout-sessions',
            CreateStripeCheckoutController::class
        )
            ->middleware(
                'throttle:api-payments-create'
            )
            ->name(
                'stripe.checkout-sessions.create'
            );

        Route::post(
            '/nowpayments/invoices',
            CreateNowPaymentsInvoiceController::class
        )
            ->middleware(
                'throttle:api-payments-create'
            )
            ->name(
                'nowpayments.invoices.create'
            );

        Route::post(
            '/bank-deposits',
            SubmitBankDepositController::class
        )
            ->middleware(
                'throttle:api-payments-create'
            )
            ->name(
                'bank-deposits.store'
            );

        Route::post(
            '/usdt-deposits',
            SubmitUsdtDepositController::class
        )
            ->middleware(
                'throttle:api-payments-create'
            )
            ->name(
                'usdt-deposits.store'
            );
    });
