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
use Illuminate\Support\Facades\DB;

class EducationPremiumEntitlementService
{
    public function hasActive(User $user): bool
    {
        return UserEntitlement::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'product_code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->where(
                'status',
                UserEntitlement::STATUS_ACTIVE
            )
            ->exists();
    }

    public function active(
        User $user
    ): ?UserEntitlement {
        return UserEntitlement::query()
            ->where(
                'user_id',
                $user->getKey()
            )
            ->where(
                'product_code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->where(
                'status',
                UserEntitlement::STATUS_ACTIVE
            )
            ->first();
    }

    public function activateFromClearedPayment(
        User $user,
        PaymentOrder $order,
        Payment $payment
    ): UserEntitlement {
        if (
            $order->product_code
                !== Product::CODE_EDUCATION_PREMIUM
            || ! $payment->isCleared()
            || (int) $order->user_id
                !== (int) $user->getKey()
            || (int) $payment->user_id
                !== (int) $user->getKey()
            || (int) $payment->payment_order_id
                !== (int) $order->getKey()
        ) {
            throw new DomainException(
                'Cleared payment does not satisfy the education premium entitlement contract.'
            );
        }

        return DB::transaction(
            function () use (
                $user,
                $order,
                $payment
            ): UserEntitlement {
                $lockedUser = User::query()
                    ->whereKey(
                        $user->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $entitlement =
                    UserEntitlement::query()
                        ->where(
                            'user_id',
                            $lockedUser
                                ->getKey()
                        )
                        ->where(
                            'product_code',
                            Product::CODE_EDUCATION_PREMIUM
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $entitlement
                        instanceof UserEntitlement
                    && $entitlement->status
                        === UserEntitlement::STATUS_ACTIVE
                    && $entitlement->payment_id
                        !== null
                    && (int) $entitlement
                        ->payment_id
                        !== (int) $payment
                            ->getKey()
                ) {
                    $boundPayment =
                        Payment::query()
                            ->find(
                                $entitlement
                                    ->payment_id
                            );

                    if (
                        $boundPayment
                            instanceof Payment
                        && $boundPayment
                            ->isCleared()
                    ) {
                        throw new EducationPremiumEntitlementConflictException(
                            'An active education premium entitlement is already backed by another cleared payment.'
                        );
                    }
                }

                if (
                    ! $entitlement
                    instanceof UserEntitlement
                ) {
                    $entitlement =
                        new UserEntitlement([
                            'user_id' =>
                                $lockedUser
                                    ->getKey(),
                            'product_code' =>
                                Product::CODE_EDUCATION_PREMIUM,
                        ]);
                }

                $firstActivation =
                    $entitlement
                        ->activated_at
                    ?? $payment
                        ->cleared_at
                    ?? now();

                $entitlement->forceFill([
                    'status' =>
                        UserEntitlement::STATUS_ACTIVE,
                    'activated_at' =>
                        $firstActivation,
                    'payment_id' =>
                        $payment->getKey(),
                    'revoked_at' =>
                        null,
                    'revoke_reason' =>
                        null,
                ])->save();

                $lockedUser->forceFill([
                    'account_type' =>
                        'PREMIUM',
                    'premium_activated_at' =>
                        $lockedUser
                            ->premium_activated_at
                        ?? $firstActivation,
                ])->save();

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action:
                        'EDUCATION_PREMIUM_ENTITLEMENT_ACTIVATED',
                    module:
                        'ENTITLEMENTS',
                    targetType:
                        'USER_ENTITLEMENT',
                    targetId:
                        $entitlement->getKey(),
                    newValues: [
                        'entitlement_uuid' =>
                            $entitlement->uuid,
                        'product_code' =>
                            Product::CODE_EDUCATION_PREMIUM,
                        'payment_uuid' =>
                            $payment->uuid,
                        'lifetime' =>
                            true,
                    ]
                );

                return $entitlement
                    ->fresh();
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

        if (
            ! $payment->order
                instanceof PaymentOrder
            || $payment->order
                ->product_code
                !== Product::CODE_EDUCATION_PREMIUM
        ) {
            return null;
        }

        return DB::transaction(
            function () use (
                $payment
            ): ?UserEntitlement {
                $lockedPayment =
                    Payment::query()
                        ->whereKey(
                            $payment->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    ! $lockedPayment
                        ->isPaymentReversed()
                ) {
                    return null;
                }

                $entitlement =
                    UserEntitlement::query()
                        ->where(
                            'user_id',
                            $lockedPayment
                                ->user_id
                        )
                        ->where(
                            'product_code',
                            Product::CODE_EDUCATION_PREMIUM
                        )
                        ->lockForUpdate()
                        ->first();

                /*
                 * Revoke only the currently-active education entitlement
                 * that is tied to this exact reversed payment.
                 * A newer cleared payment or legacy payment_id=NULL
                 * entitlement is not touched.
                 */
                if (
                    ! $entitlement
                        instanceof UserEntitlement
                    || $entitlement->status
                        !== UserEntitlement::STATUS_ACTIVE
                    || $entitlement->payment_id
                        === null
                    || (int) $entitlement
                        ->payment_id
                        !== (int) $lockedPayment
                            ->getKey()
                ) {
                    return null;
                }

                $entitlement->forceFill([
                    'status' =>
                        UserEntitlement::STATUS_REVOKED,
                    'revoked_at' =>
                        now(),
                    'revoke_reason' =>
                        'Backing payment was reversed by the payment processor.',
                ])->save();

                $user = User::query()
                    ->whereKey(
                        $lockedPayment->user_id
                    )
                    ->lockForUpdate()
                    ->first();

                if (
                    $user instanceof User
                    && ! UserEntitlement::query()
                        ->where(
                            'user_id',
                            $user->getKey()
                        )
                        ->where(
                            'product_code',
                            Product::CODE_EDUCATION_PREMIUM
                        )
                        ->where(
                            'status',
                            UserEntitlement::STATUS_ACTIVE
                        )
                        ->exists()
                ) {
                    /*
                     * account_type remains a legacy/UI mirror only.
                     * premium_activated_at is historical and is preserved.
                     */
                    $user->forceFill([
                        'account_type' =>
                            'FREE',
                    ])->save();
                }

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action:
                        'EDUCATION_PREMIUM_ENTITLEMENT_REVOKED_FOR_PAYMENT_REVERSAL',
                    module:
                        'ENTITLEMENTS',
                    targetType:
                        'USER_ENTITLEMENT',
                    targetId:
                        $entitlement->getKey(),
                    oldValues: [
                        'status' =>
                            UserEntitlement::STATUS_ACTIVE,
                    ],
                    newValues: [
                        'status' =>
                            UserEntitlement::STATUS_REVOKED,
                        'entitlement_uuid' =>
                            $entitlement->uuid,
                        'payment_uuid' =>
                            $lockedPayment->uuid,
                        'copy_trade_force_close' =>
                            false,
                    ]
                );

                return $entitlement
                    ->fresh();
            },
            3
        );
    }
}
