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
        private readonly EducationPremiumEntitlementService $entitlements
    ) {
    }

    public function reconcile(
        Payment $payment
    ): UserEntitlement {
        $payment->loadMissing('order');

        $order = $payment->order;

        if (
            ! $order instanceof PaymentOrder
            || $order->product_code
                !== Product::CODE_EDUCATION_PREMIUM
            || ! $payment->isCleared()
            || (int) $order->user_id
                !== (int) $payment->user_id
            || (int) $payment->payment_order_id
                !== (int) $order->getKey()
        ) {
            throw new DomainException(
                'Cleared payment does not satisfy the education premium reconciliation contract.'
            );
        }

        $user = User::query()
            ->find($payment->user_id);

        if (! $user instanceof User) {
            throw new DomainException(
                'Cleared payment user is unavailable for education premium reconciliation.'
            );
        }

        try {
            return $this->entitlements
                ->activateFromClearedPayment(
                    $user,
                    $order,
                    $payment
                );
        } catch (
            EducationPremiumEntitlementConflictException $exception
        ) {
            $active = $this->entitlements
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

            /*
             * The conflict may have resolved after the entitlement service
             * released its transaction lock, for example if the prior
             * backing payment was reversed concurrently. Retry once so the
             * new cleared payment can become the authoritative backing row.
             */
            if (
                ! $boundPayment instanceof Payment
                || ! $boundPayment->isCleared()
            ) {
                return $this->entitlements
                    ->activateFromClearedPayment(
                        $user->fresh(),
                        $order->fresh(),
                        $payment->fresh()
                    );
            }

            /*
             * A second externally-cleared payment must never silently rebind
             * the lifetime entitlement away from another still-cleared
             * payment. The money ledger remains authoritative and durable,
             * while the duplicate payment is surfaced for payment review.
             */
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
