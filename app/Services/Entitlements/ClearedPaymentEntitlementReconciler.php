<?php

namespace App\Services\Entitlements;

use App\Exceptions\EducationPremiumEntitlementConflictException;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\Product;
use App\Models\User;
use App\Models\UserEntitlement;
use DomainException;

class ClearedPaymentEntitlementReconciler
{
    public function __construct(
        private readonly EducationPremiumEntitlementService
            $educationPremium,
        private readonly GenericProductEntitlementService
            $genericProducts
    ) {
    }

    public function reconcile(
        Payment $payment
    ): UserEntitlement {
        $payment->loadMissing('order');
        $order = $payment->order;

        if (
            ! $order instanceof PaymentOrder
            || ! $payment->isCleared()
            || (int) $order->user_id
                !== (int) $payment->user_id
            || (int) $payment->payment_order_id
                !== (int) $order->getKey()
        ) {
            throw new DomainException(
                'Cleared payment does not satisfy the product entitlement reconciliation contract.'
            );
        }

        $user = User::query()
            ->find($payment->user_id);

        if (! $user instanceof User) {
            throw new DomainException(
                'Cleared payment user is unavailable for entitlement reconciliation.'
            );
        }

        if (
            $order->product_code
                !== Product::CODE_EDUCATION_PREMIUM
        ) {
            return $this->genericProducts
                ->activateFromClearedPayment(
                    $user,
                    $order,
                    $payment
                );
        }

        return $this->reconcileEducationPremium(
            $user,
            $order,
            $payment
        );
    }

    private function reconcileEducationPremium(
        User $user,
        PaymentOrder $order,
        Payment $payment
    ): UserEntitlement {
        try {
            return $this->educationPremium
                ->activateFromClearedPayment(
                    $user,
                    $order,
                    $payment
                );
        } catch (
            EducationPremiumEntitlementConflictException $exception
        ) {
            $active = $this->educationPremium
                ->active($user);

            if (
                ! $active instanceof UserEntitlement
                || $active->payment_id === null
                || (int) $active->payment_id
                    === (int) $payment->getKey()
            ) {
                throw $exception;
            }

            $boundPayment = Payment::query()
                ->find($active->payment_id);

            if (
                ! $boundPayment instanceof Payment
                || ! $boundPayment->isCleared()
            ) {
                return $this->educationPremium
                    ->activateFromClearedPayment(
                        $user->fresh(),
                        $order->fresh(),
                        $payment->fresh()
                    );
            }

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action:
                    'EDUCATION_PREMIUM_ENTITLEMENT_DUPLICATE_CLEARED_PAYMENT_REVIEW',
                module: 'ENTITLEMENTS',
                targetType: 'USER_ENTITLEMENT',
                targetId: $active->getKey(),
                newValues: [
                    'entitlement_uuid' =>
                        $active->uuid,
                    'kept_payment_uuid' =>
                        $boundPayment->uuid,
                    'duplicate_payment_uuid' =>
                        $payment->uuid,
                    'entitlement_rebound' =>
                        false,
                    'payment_review_required' =>
                        true,
                ]
            );

            return $active->fresh();
        }
    }
}
