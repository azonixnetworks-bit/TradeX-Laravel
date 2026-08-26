<?php

namespace App\Services\Payments;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Support\Facades\DB;

class OnlineGatewaySettlementService
{
    public function settle(
        PaymentOrder $order,
        string $provider,
        string $providerPaymentReference,
        string $currency,
        int $grossMinor,
        ?string $providerEventId = null
    ): Payment {
        $provider = strtoupper(trim($provider));
        $providerPaymentReference = trim($providerPaymentReference);
        $currency = strtoupper(trim($currency));

        if (! in_array($provider, [
            PaymentOrder::PROVIDER_STRIPE,
            PaymentOrder::PROVIDER_NOWPAYMENTS,
        ], true)) {
            throw new AuthApiException(422, 'PAYMENT_PROVIDER_UNSUPPORTED', 'This online payment provider is not supported by API-21 settlement.');
        }

        if ($order->provider !== $provider) {
            throw new AuthApiException(409, 'PAYMENT_PROVIDER_ORDER_MISMATCH', 'The provider does not match the TradeX payment order.');
        }

        if ($providerPaymentReference === '' || mb_strlen($providerPaymentReference) > 191) {
            throw new AuthApiException(422, 'PAYMENT_PROVIDER_REFERENCE_INVALID', 'The provider payment reference is invalid.');
        }

        if (
            $currency === ''
            || $grossMinor <= 0
            || $currency !== strtoupper((string) $order->currency)
            || $grossMinor !== (int) $order->amount_minor
        ) {
            throw new AuthApiException(409, 'PAYMENT_PROVIDER_AMOUNT_MISMATCH', 'The provider payment amount does not match the TradeX order.');
        }

        $referenceHash = hash('sha256', $provider.'|'.$providerPaymentReference);

        return DB::transaction(function () use (
            $order,
            $provider,
            $providerPaymentReference,
            $currency,
            $grossMinor,
            $providerEventId,
            $referenceHash
        ): Payment {
            $lockedOrder = PaymentOrder::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->isPaymentReversed()) {
                throw new AuthApiException(409, 'PAYMENT_ALREADY_REVERSED', 'This payment order has already been reversed.');
            }

            if ($lockedOrder->provider !== $provider) {
                throw new AuthApiException(409, 'PAYMENT_PROVIDER_ORDER_MISMATCH', 'The provider does not match the TradeX payment order.');
            }

            $existing = Payment::query()
                ->where('provider_capture_reference_hash', $referenceHash)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Payment) {
                if (
                    (int) $existing->payment_order_id !== (int) $lockedOrder->getKey()
                    || $existing->provider !== $provider
                    || strtoupper((string) $existing->currency) !== $currency
                    || (int) $existing->gross_amount_minor !== $grossMinor
                ) {
                    throw new AuthApiException(409, 'PAYMENT_PROVIDER_REFERENCE_CONFLICT', 'This provider payment reference is already linked to different payment data.');
                }

                if (! $lockedOrder->isCleared()) {
                    $lockedOrder->forceFill([
                        'status' => PaymentOrder::STATUS_CLEARED,
                        'cleared_at' => $existing->cleared_at ?? now(),
                        'failure_code' => null,
                    ])->save();
                }

                return $existing->refresh();
            }

            $existingForOrder = Payment::query()
                ->where('payment_order_id', $lockedOrder->getKey())
                ->lockForUpdate()
                ->first();

            if ($existingForOrder instanceof Payment) {
                throw new AuthApiException(409, 'PAYMENT_ORDER_ALREADY_SETTLED', 'This TradeX payment order is already linked to another provider payment reference.');
            }

            $user = User::query()
                ->whereKey($lockedOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = Payment::query()->create([
                'payment_order_id' => $lockedOrder->getKey(),
                'user_id' => $user->getKey(),
                'provider' => $provider,
                'currency' => $currency,
                'gross_amount_minor' => $grossMinor,
                'processor_fee_minor' => null,
                'net_eligible_revenue_minor' => null,
                'provider_capture_reference_hash' => $referenceHash,
                'provider_capture_reference' => $providerPaymentReference,
                'status' => Payment::STATUS_CLEARED,
                'affiliate_processing_status' => Payment::AFFILIATE_NOT_APPLICABLE,
                'cleared_at' => now(),
            ]);

            $lockedOrder->forceFill([
                'status' => PaymentOrder::STATUS_CLEARED,
                'cleared_at' => $payment->cleared_at,
                'failure_code' => null,
            ])->save();

            if (! $user->isPremium()) {
                $user->forceFill([
                    'account_type' => 'PREMIUM',
                    'premium_activated_at' => $user->premium_activated_at ?? now(),
                ])->save();
            }

            UserEntitlement::query()->updateOrCreate([
                'user_id' => $user->getKey(),
                'product_code' => $lockedOrder->product_code,
            ], [
                'status' => UserEntitlement::STATUS_ACTIVE,
                'activated_at' => now(),
                'payment_id' => $payment->getKey(),
                'revoked_at' => null,
                'revoke_reason' => null,
            ]);

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'PAYMENT_CLEARED',
                module: 'PAYMENTS',
                targetType: 'PAYMENT',
                targetId: $payment->getKey(),
                newValues: [
                    'payment_uuid' => $payment->uuid,
                    'payment_order_uuid' => $lockedOrder->uuid,
                    'provider' => $provider,
                    'currency' => $currency,
                    'gross_amount_minor' => $grossMinor,
                    'processor_fee_minor' => null,
                    'net_eligible_revenue_minor' => null,
                    'provider_event_recorded' => $providerEventId !== null,
                    'affiliate_runtime_extended' => false,
                ]
            );

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'PREMIUM_ENTITLEMENT_ACTIVATED',
                module: 'PAYMENTS',
                targetType: 'USER',
                targetId: $user->getKey(),
                newValues: [
                    'product_code' => $lockedOrder->product_code,
                    'payment_uuid' => $payment->uuid,
                    'provider' => $provider,
                ]
            );

            return $payment->fresh();
        }, 3);
    }
}
