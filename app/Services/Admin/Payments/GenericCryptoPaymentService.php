<?php

namespace App\Services\Admin\Payments;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CryptoDeposit;
use App\Models\Payment;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Entitlements\ClearedPaymentEntitlementReconciler;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GenericCryptoPaymentService
{
    private const MANUAL_REVIEW_REJECTED =
        'MANUAL_REVIEW_REJECTED';

    public function __construct(
        private readonly
            ClearedPaymentEntitlementReconciler
            $entitlements
    ) {
    }

    public function deposits(
        array $filters
    ) {
        $query =
            CryptoDeposit::query()
                ->with([
                    'order:id,uuid,user_id,provider,country_code,currency,amount_minor,status,cleared_at,failure_code,created_at',
                    'user:id,uuid,name,email,country_code,account_type,status',
                    'reviewedByAdmin:id,name,email',
                ]);

        $search =
            trim(
                (string) (
                    $filters['search']
                    ?? ''
                )
            );

        if (
            $search !== ''
        ) {
            $query->where(
                function (
                    $builder
                ) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'uuid',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'tx_hash',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'coin_symbol',
                            'like',
                            '%'.strtoupper(
                                $search
                            ).'%'
                        )
                        ->orWhere(
                            'network',
                            'like',
                            '%'.strtoupper(
                                $search
                            ).'%'
                        )
                        ->orWhereHas(
                            'order',
                            function (
                                $order
                            ) use (
                                $search
                            ): void {
                                $order
                                    ->where(
                                        'uuid',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        )
                        ->orWhereHas(
                            'user',
                            function (
                                $user
                            ) use (
                                $search
                            ): void {
                                $user
                                    ->where(
                                        'name',
                                        'like',
                                        '%'.$search.'%'
                                    )
                                    ->orWhere(
                                        'email',
                                        'like',
                                        '%'.$search.'%'
                                    );
                            }
                        );
                }
            );
        }

        $status =
            trim(
                (string) (
                    $filters['status']
                    ?? ''
                )
            );

        if (
            in_array(
                $status,
                [
                    CryptoDeposit::STATUS_SUBMITTED,
                    CryptoDeposit::STATUS_APPROVED,
                    CryptoDeposit::STATUS_REJECTED,
                ],
                true
            )
        ) {
            $query->where(
                'status',
                $status
            );
        }

        $from =
            trim(
                (string) (
                    $filters['from']
                    ?? ''
                )
            );

        if (
            $from !== ''
        ) {
            $query->whereDate(
                'submitted_at',
                '>=',
                $from
            );
        }

        $to =
            trim(
                (string) (
                    $filters['to']
                    ?? ''
                )
            );

        if (
            $to !== ''
        ) {
            $query->whereDate(
                'submitted_at',
                '<=',
                $to
            );
        }

        $perPage =
            (int) (
                $filters['per_page']
                ?? 20
            );

        $perPage =
            in_array(
                $perPage,
                [
                    10,
                    20,
                    50,
                    100,
                ],
                true
            )
                ? $perPage
                : 20;

        return $query
            ->orderByDesc(
                'submitted_at'
            )
            ->orderByDesc(
                'id'
            )
            ->paginate(
                $perPage
            )
            ->withQueryString();
    }

    public function approve(
        CryptoDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): Payment {
        /*
         * Compatibility repair path for a generic-crypto payment that may
         * already have been cleared by an earlier local ADMIN-23B2 build.
         * New approvals reconcile the entitlement inside the same outer
         * transaction and therefore roll back atomically on failure.
         */
        $retryPayment =
            $this->approvedPaymentForRetry(
                $deposit
            );

        if (
            $retryPayment
            instanceof Payment
        ) {
            $this->entitlements
                ->reconcile(
                    $retryPayment
                );

            return $retryPayment
                ->fresh();
        }

        $payment =
            DB::transaction(
                function () use (
                    $deposit,
                    $admin,
                    $reason,
                    $request
                ): Payment {
                    $locked =
                        CryptoDeposit::query()
                            ->whereKey(
                                $deposit
                                    ->getKey()
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $order =
                        PaymentOrder::query()
                            ->whereKey(
                                $locked
                                    ->payment_order_id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    $this->assertReviewable(
                        $order,
                        $locked
                    );

                    $user =
                        User::query()
                            ->whereKey(
                                $order
                                    ->user_id
                            )
                            ->lockForUpdate()
                            ->firstOrFail();

                    if (
                        Payment::query()
                            ->where(
                                'payment_order_id',
                                $order
                                    ->getKey()
                            )
                            ->lockForUpdate()
                            ->exists()
                    ) {
                        throw new DomainException(
                            'A settled payment already exists for this order.'
                        );
                    }

                    $reference =
                        'ADMIN_CRYPTO:'
                        .$locked
                            ->uuid;

                    $referenceHash =
                        hash(
                            'sha256',
                            $reference
                        );

                    if (
                        Payment::query()
                            ->where(
                                'provider_capture_reference_hash',
                                $referenceHash
                            )
                            ->lockForUpdate()
                            ->exists()
                    ) {
                        throw new DomainException(
                            'This crypto payment evidence has already been reconciled.'
                        );
                    }

                    $payment =
                        Payment::query()
                            ->create([
                                'payment_order_id' =>
                                    $order
                                        ->getKey(),
                                'user_id' =>
                                    $user
                                        ->getKey(),
                                'provider' =>
                                    PaymentOrder::PROVIDER_CRYPTO_MANUAL,
                                'currency' =>
                                    strtoupper(
                                        (string) $order
                                            ->currency
                                    ),
                                'gross_amount_minor' =>
                                    (int) $order
                                        ->amount_minor,
                                'processor_fee_minor' =>
                                    null,
                                'net_eligible_revenue_minor' =>
                                    null,
                                'provider_capture_reference_hash' =>
                                    $referenceHash,
                                'provider_capture_reference' =>
                                    $reference,
                                'status' =>
                                    Payment::STATUS_CLEARED,
                                'affiliate_processing_status' =>
                                    Payment::AFFILIATE_NOT_APPLICABLE,
                                'affiliate_processed_at' =>
                                    null,
                                'cleared_at' =>
                                    now(),
                            ]);

                    $locked
                        ->forceFill([
                            'status' =>
                                CryptoDeposit::STATUS_APPROVED,
                            'reviewed_by_admin_id' =>
                                $admin
                                    ->getKey(),
                            'reviewed_at' =>
                                now(),
                            'review_reason' =>
                                trim(
                                    $reason
                                ),
                        ])
                        ->save();

                    $order
                        ->forceFill([
                            'status' =>
                                PaymentOrder::STATUS_CLEARED,
                            'cleared_at' =>
                                $payment
                                    ->cleared_at,
                            'failure_code' =>
                                null,
                        ])
                        ->save();

                    $this->auditDecision(
                        $admin,
                        $order,
                        $payment,
                        $locked,
                        'PAYMENT_CRYPTO_DEPOSIT_VERIFIED',
                        CryptoDeposit::STATUS_APPROVED,
                        $request
                    );

                    $this->auditCleared(
                        $admin,
                        $order,
                        $payment,
                        $locked,
                        $request
                    );

                    /*
                     * Manual admin settlement is internally reversible.
                     * Reconcile before the outer transaction commits so an
                     * unexpected entitlement error rolls back deposit review,
                     * order clearing and the payment row together.
                     */
                    $this->entitlements
                        ->reconcile(
                            $payment
                        );

                    return $payment
                        ->fresh();
                },
                3
            );

        return $payment
            ->fresh();
    }

    public function reject(
        CryptoDeposit $deposit,
        AdminUser $admin,
        string $reason,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $deposit,
                $admin,
                $reason,
                $request
            ): void {
                $locked =
                    CryptoDeposit::query()
                        ->whereKey(
                            $deposit
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $order =
                    PaymentOrder::query()
                        ->whereKey(
                            $locked
                                ->payment_order_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assertReviewable(
                    $order,
                    $locked
                );

                if (
                    Payment::query()
                        ->where(
                            'payment_order_id',
                            $order
                                ->getKey()
                        )
                        ->lockForUpdate()
                        ->exists()
                ) {
                    throw new DomainException(
                        'A settled payment already exists for this order.'
                    );
                }

                $locked
                    ->forceFill([
                        'status' =>
                            CryptoDeposit::STATUS_REJECTED,
                        'reviewed_by_admin_id' =>
                            $admin
                                ->getKey(),
                        'reviewed_at' =>
                            now(),
                        'review_reason' =>
                            trim(
                                $reason
                            ),
                    ])
                    ->save();

                $order
                    ->forceFill([
                        'status' =>
                            PaymentOrder::STATUS_FAILED,
                        'failure_code' =>
                            self::MANUAL_REVIEW_REJECTED,
                        'cleared_at' =>
                            null,
                    ])
                    ->save();

                $this->auditDecision(
                    $admin,
                    $order,
                    null,
                    $locked,
                    'PAYMENT_CRYPTO_DEPOSIT_REJECTED',
                    CryptoDeposit::STATUS_REJECTED,
                    $request
                );
            },
            3
        );
    }

    private function approvedPaymentForRetry(
        CryptoDeposit $deposit
    ): ?Payment {
        $current =
            CryptoDeposit::query()
                ->with([
                    'order.payment',
                ])
                ->whereKey(
                    $deposit
                        ->getKey()
                )
                ->first();

        if (
            ! $current
            instanceof CryptoDeposit
        ) {
            return null;
        }

        if (
            $current->status
                === CryptoDeposit::STATUS_SUBMITTED
        ) {
            return null;
        }

        $order =
            $current->order;

        $payment =
            $order?->payment;

        if (
            $current->status
                === CryptoDeposit::STATUS_APPROVED
            && $order
                instanceof PaymentOrder
            && $order->provider
                === PaymentOrder::PROVIDER_CRYPTO_MANUAL
            && $order->status
                === PaymentOrder::STATUS_CLEARED
            && $payment
                instanceof Payment
            && $payment->isCleared()
        ) {
            return $payment;
        }

        throw new DomainException(
            'This generic crypto payment has already been reviewed or is no longer valid for approval.'
        );
    }

    private function assertReviewable(
        PaymentOrder $order,
        CryptoDeposit $deposit
    ): void {
        if (
            $order->provider
                !== PaymentOrder::PROVIDER_CRYPTO_MANUAL
            || $order->status
                !== PaymentOrder::STATUS_PENDING_REVIEW
            || $deposit->status
                !== CryptoDeposit::STATUS_SUBMITTED
            || (int) $deposit
                ->user_id
                !== (int) $order
                    ->user_id
        ) {
            throw new DomainException(
                'This generic crypto payment is no longer reviewable.'
            );
        }

        $snapshot =
            (array) (
                $order
                    ->receiving_account_snapshot
                ?? []
            );

        $expectedCoin =
            strtoupper(
                trim(
                    (string) (
                        $snapshot[
                            'coin_symbol'
                        ]
                        ?? ''
                    )
                )
            );

        $expectedNetwork =
            strtoupper(
                trim(
                    (string) (
                        $snapshot[
                            'network'
                        ]
                        ?? ''
                    )
                )
            );

        $expectedAmount =
            (string) (
                $snapshot[
                    'amount'
                ]
                ?? ''
            );

        $expectedScale =
            (int) (
                $snapshot[
                    'amount_scale'
                ]
                ?? -1
            );

        if (
            $expectedCoin === ''
            || $expectedNetwork === ''
            || $expectedAmount === ''
            || $expectedScale < 0
            || $deposit
                ->coin_symbol
                !== $expectedCoin
            || $deposit
                ->network
                !== $expectedNetwork
            || $deposit
                ->amount_decimal
                !== $expectedAmount
            || (int) $deposit
                ->amount_scale
                !== $expectedScale
        ) {
            throw new DomainException(
                'Crypto evidence does not match the immutable TradeX payment contract.'
            );
        }
    }

    private function auditDecision(
        AdminUser $admin,
        PaymentOrder $order,
        ?Payment $payment,
        CryptoDeposit $deposit,
        string $action,
        string $decision,
        Request $request
    ): void {
        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin
                    ->getKey(),
            action:
                $action,
            module:
                'PAYMENTS',
            targetType:
                'PAYMENT_ORDER',
            targetId:
                $order
                    ->getKey(),
            newValues: [
                'order_uuid' =>
                    $order
                        ->uuid,
                'payment_uuid' =>
                    $payment?->uuid,
                'provider' =>
                    $order
                        ->provider,
                'coin_symbol' =>
                    $deposit
                        ->coin_symbol,
                'network' =>
                    $deposit
                        ->network,
                'amount_decimal' =>
                    $deposit
                        ->amount_decimal,
                'amount_scale' =>
                    (int) $deposit
                        ->amount_scale,
                'decision' =>
                    $decision,
            ],
            ipAddress:
                $request
                    ->ip(),
            userAgent:
                $request
                    ->userAgent()
        );
    }

    private function auditCleared(
        AdminUser $admin,
        PaymentOrder $order,
        Payment $payment,
        CryptoDeposit $deposit,
        Request $request
    ): void {
        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin
                    ->getKey(),
            action:
                'PAYMENT_CLEARED',
            module:
                'PAYMENTS',
            targetType:
                'PAYMENT',
            targetId:
                $payment
                    ->getKey(),
            newValues: [
                'payment_uuid' =>
                    $payment
                        ->uuid,
                'payment_order_uuid' =>
                    $order
                        ->uuid,
                'provider' =>
                    $order
                        ->provider,
                'currency' =>
                    $order
                        ->currency,
                'gross_amount_minor' =>
                    (int) $order
                        ->amount_minor,
                'manual_reconciliation' =>
                    true,
                'crypto_coin_symbol' =>
                    $deposit
                        ->coin_symbol,
                'crypto_network' =>
                    $deposit
                        ->network,
                'affiliate_processing_status' =>
                    Payment::AFFILIATE_NOT_APPLICABLE,
            ],
            ipAddress:
                $request
                    ->ip(),
            userAgent:
                $request
                    ->userAgent()
        );
    }
}
