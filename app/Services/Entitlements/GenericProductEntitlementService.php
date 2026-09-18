<?php

namespace App\Services\Entitlements;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\UserEntitlement;
use DomainException;
use Illuminate\Support\Facades\DB;

class GenericProductEntitlementService
{
    public function hasActive(
        User $user,
        string $productCode
    ): bool {
        return $this->active(
            $user,
            $productCode
        ) instanceof UserEntitlement;
    }

    public function active(
        User $user,
        string $productCode
    ): ?UserEntitlement {
        return UserEntitlement::query()
            ->where('user_id', $user->getKey())
            ->where('product_code', strtoupper(trim($productCode)))
            ->where('status', UserEntitlement::STATUS_ACTIVE)
            ->first();
    }

    public function activateFromClearedPayment(
        User $user,
        PaymentOrder $order,
        Payment $payment
    ): UserEntitlement {
        $product = Product::query()
            ->where('code', $order->product_code)
            ->first();

        if (
            ! $product instanceof Product
            || $product->isEducationPremium()
            || $product->billing_type
                !== Product::BILLING_ONE_TIME_LIFETIME
            || ! $payment->isCleared()
            || (int) $order->user_id
                !== (int) $user->getKey()
            || (int) $payment->user_id
                !== (int) $user->getKey()
            || (int) $payment->payment_order_id
                !== (int) $order->getKey()
        ) {
            throw new DomainException(
                'Cleared payment does not satisfy the generic product entitlement contract.'
            );
        }

        return DB::transaction(
            function () use (
                $user,
                $order,
                $payment,
                $product
            ): UserEntitlement {
                $lockedUser = User::query()
                    ->whereKey($user->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $entitlement = UserEntitlement::query()
                    ->where('user_id', $lockedUser->getKey())
                    ->where('product_code', $product->code)
                    ->lockForUpdate()
                    ->first();

                if (
                    $entitlement instanceof UserEntitlement
                    && $entitlement->status
                        === UserEntitlement::STATUS_ACTIVE
                ) {
                    if (
                        $entitlement->payment_id === null
                        || (int) $entitlement->payment_id
                            === (int) $payment->getKey()
                    ) {
                        return $entitlement->fresh();
                    }

                    $boundPayment = Payment::query()
                        ->find($entitlement->payment_id);

                    if (
                        $boundPayment instanceof Payment
                        && $boundPayment->isCleared()
                    ) {
                        AuditLog::record(
                            actorType: 'SYSTEM',
                            actorId: null,
                            action: 'PRODUCT_ENTITLEMENT_DUPLICATE_CLEARED_PAYMENT_REVIEW',
                            module: 'ENTITLEMENTS',
                            targetType: 'USER_ENTITLEMENT',
                            targetId: $entitlement->getKey(),
                            newValues: [
                                'entitlement_uuid' => $entitlement->uuid,
                                'product_code' => $product->code,
                                'kept_payment_uuid' => $boundPayment->uuid,
                                'duplicate_payment_uuid' => $payment->uuid,
                                'entitlement_rebound' => false,
                                'payment_review_required' => true,
                            ]
                        );

                        return $entitlement->fresh();
                    }
                }

                if (! $entitlement instanceof UserEntitlement) {
                    $entitlement = new UserEntitlement([
                        'user_id' => $lockedUser->getKey(),
                        'product_code' => $product->code,
                    ]);
                }

                $firstActivation = $entitlement->activated_at
                    ?? $payment->cleared_at
                    ?? now();

                $entitlement->forceFill([
                    'status' => UserEntitlement::STATUS_ACTIVE,
                    'activated_at' => $firstActivation,
                    'payment_id' => $payment->getKey(),
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PRODUCT_ENTITLEMENT_ACTIVATED',
                    module: 'ENTITLEMENTS',
                    targetType: 'USER_ENTITLEMENT',
                    targetId: $entitlement->getKey(),
                    newValues: [
                        'entitlement_uuid' => $entitlement->uuid,
                        'product_code' => $product->code,
                        'payment_uuid' => $payment->uuid,
                        'lifetime' => true,
                    ]
                );

                return $entitlement->fresh();
            },
            3
        );
    }

    public function revokeForReversedPayment(
        Payment $payment
    ): ?UserEntitlement {
        if (! $payment->isPaymentReversed()) {
            return null;
        }

        $payment->loadMissing('order');
        $order = $payment->order;

        if (
            ! $order instanceof PaymentOrder
            || $order->product_code === Product::CODE_EDUCATION_PREMIUM
        ) {
            return null;
        }

        return DB::transaction(
            function () use ($payment, $order): ?UserEntitlement {
                $lockedPayment = Payment::query()
                    ->whereKey($payment->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $lockedPayment->isPaymentReversed()) {
                    return null;
                }

                $entitlement = UserEntitlement::query()
                    ->where('user_id', $lockedPayment->user_id)
                    ->where('product_code', $order->product_code)
                    ->lockForUpdate()
                    ->first();

                if (
                    ! $entitlement instanceof UserEntitlement
                    || $entitlement->status
                        !== UserEntitlement::STATUS_ACTIVE
                    || $entitlement->payment_id === null
                    || (int) $entitlement->payment_id
                        !== (int) $lockedPayment->getKey()
                ) {
                    return null;
                }

                $entitlement->forceFill([
                    'status' => UserEntitlement::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoke_reason' =>
                        'Backing payment was reversed by the payment processor.',
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action: 'PRODUCT_ENTITLEMENT_REVOKED_FOR_PAYMENT_REVERSAL',
                    module: 'ENTITLEMENTS',
                    targetType: 'USER_ENTITLEMENT',
                    targetId: $entitlement->getKey(),
                    oldValues: [
                        'status' => UserEntitlement::STATUS_ACTIVE,
                    ],
                    newValues: [
                        'status' => UserEntitlement::STATUS_REVOKED,
                        'entitlement_uuid' => $entitlement->uuid,
                        'product_code' => $order->product_code,
                        'payment_uuid' => $lockedPayment->uuid,
                    ]
                );

                return $entitlement->fresh();
            },
            3
        );
    }
}
