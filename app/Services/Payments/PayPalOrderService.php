<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\PaymentOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayPalOrderService
{
    public function __construct(
        private readonly PaymentMethodService
            $methods,
        private readonly PaymentOrderService
            $orders,
        private readonly PayPalGatewayService
            $paypal,
        private readonly PaymentSettlementService
            $settlement,
        private readonly PaymentApiPresenter
            $presenter
    ) {
    }

    public function create(
        User $user,
        string $idempotencyKey,
        Request $request
    ): array {
        $method = $this->methods
            ->assertAvailable(
                $user,
                PaymentOrder::PROVIDER_PAYPAL
            );

        $hash = $this->orders
            ->idempotencyHash(
                $idempotencyKey
            );

        $existing = PaymentOrder::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'client_idempotency_key_hash',
                $hash
            )
            ->first();

        if ($existing instanceof PaymentOrder) {
            $this->assertIdempotentProvider(
                $existing,
                PaymentOrder::PROVIDER_PAYPAL
            );

            return $this->presenter
                ->order(
                    $existing->fresh([
                        'payment',
                        'bankDeposit',
                        'usdtDeposit',
                    ])
                );
        }

        $amount = $method['amount'];

        $order = PaymentOrder::query()
            ->create([
                'user_id' =>
                    $user->getKey(),
                'product_code' =>
                    PaymentOrder::PRODUCT_EDUCATION_PREMIUM,
                'provider' =>
                    PaymentOrder::PROVIDER_PAYPAL,
                'country_code' =>
                    strtoupper(
                        (string) $user
                            ->country_code
                    ),
                'currency' =>
                    $amount['currency'],
                'amount_minor' =>
                    (int) $amount[
                        'amount_minor'
                    ],
                'status' =>
                    PaymentOrder::STATUS_CREATED,
                'client_idempotency_key_hash' =>
                    $hash,
            ]);

        try {
            $provider =
                $this->paypal
                    ->createOrder(
                        $order
                    );
        } catch (\Throwable $exception) {
            $order->forceFill([
                'status' =>
                    PaymentOrder::STATUS_FAILED,
                'failure_code' =>
                    $exception
                    instanceof AuthApiException
                        ? $exception->apiCode
                        : 'PAYPAL_ORDER_CREATE_FAILED',
            ])->save();

            throw $exception;
        }

        $order->forceFill([
            'status' =>
                PaymentOrder::STATUS_AWAITING_USER_ACTION,
            'provider_reference' =>
                $provider[
                    'provider_order_id'
                ],
            'approval_url' =>
                $provider[
                    'approval_url'
                ],
            'failure_code' =>
                null,
        ])->save();

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'PAYMENT_PAYPAL_ORDER_CREATED',
            module: 'PAYMENTS',
            targetType: 'PAYMENT_ORDER',
            targetId: $order->getKey(),
            newValues: [
                'payment_order_uuid' =>
                    $order->uuid,
                'provider' =>
                    $order->provider,
                'product_code' =>
                    $order->product_code,
                'currency' =>
                    $order->currency,
                'amount_minor' =>
                    $order->amount_minor,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->presenter
            ->order(
                $order->fresh([
                    'payment',
                    'bankDeposit',
                    'usdtDeposit',
                ])
            );
    }

    public function capture(
        User $user,
        string $orderUuid,
        Request $request
    ): array {
        $order = $this->orders
            ->resolveOwned(
                $user,
                $orderUuid
            );

        if (
            $order->provider
            !== PaymentOrder::PROVIDER_PAYPAL
        ) {
            throw new AuthApiException(
                409,
                'PAYMENT_PROVIDER_MISMATCH',
                'This payment order is not a PayPal order.'
            );
        }

        if ($order->isCleared()) {
            return $this->presenter
                ->order(
                    $order
                );
        }

        if ($order->isPaymentReversed()) {
            throw new AuthApiException(
                409,
                'PAYMENT_ALREADY_REVERSED',
                'This payment has already been reversed by the payment processor.'
            );
        }

        if (! in_array(
            $order->status,
            [
                PaymentOrder::STATUS_AWAITING_USER_ACTION,
                PaymentOrder::STATUS_PROCESSING,
                PaymentOrder::STATUS_FAILED,
            ],
            true
        )) {
            throw new AuthApiException(
                409,
                'PAYPAL_ORDER_NOT_CAPTURABLE',
                'This PayPal order is not ready for capture.'
            );
        }

        $order->forceFill([
            'status' =>
                PaymentOrder::STATUS_PROCESSING,
            'failure_code' =>
                null,
        ])->save();

        try {
            $capture = $this->paypal
                ->captureOrder(
                    $order
                );

            $this->settlement
                ->settlePayPalCapture(
                    $order,
                    $capture
                );
        } catch (\Throwable $exception) {
            $fresh = $order->fresh();

            if (
                ! $fresh->isCleared()
                && ! $fresh->isPaymentReversed()
            ) {
                $fresh->forceFill([
                    'status' =>
                        PaymentOrder::STATUS_FAILED,
                    'failure_code' =>
                        $exception
                        instanceof AuthApiException
                            ? $exception->apiCode
                            : 'PAYPAL_CAPTURE_FAILED',
                ])->save();
            }

            throw $exception;
        }

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'PAYMENT_PAYPAL_CAPTURE_CONFIRMED',
            module: 'PAYMENTS',
            targetType: 'PAYMENT_ORDER',
            targetId: $order->getKey(),
            newValues: [
                'payment_order_uuid' =>
                    $order->uuid,
                'status' =>
                    PaymentOrder::STATUS_CLEARED,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return $this->presenter
            ->order(
                $order->fresh([
                    'payment',
                    'bankDeposit',
                    'usdtDeposit',
                ])
            );
    }

    private function assertIdempotentProvider(
        PaymentOrder $order,
        string $provider
    ): void {
        if ($order->provider !== $provider) {
            throw new AuthApiException(
                409,
                'IDEMPOTENCY_KEY_CONFLICT',
                'This idempotency key was already used for a different payment request.'
            );
        }
    }
}
