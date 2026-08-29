<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBillingCycle;
use App\Models\CopyTradeBillingInvoice;
use App\Models\CopyTradeBillingPaymentEvent;
use App\Models\CopyTradeBrokerAccount;
use App\Models\Payment;
use App\Models\UserEntitlement;
use DomainException;
use Illuminate\Support\Facades\DB;

class CopyTradeBillingPaymentService
{
    public function linkClearedPayment(
        CopyTradeBillingInvoice $invoice,
        Payment $payment,
        ?AdminUser $admin = null
    ): CopyTradeBillingInvoice {
        if (! $payment->isCleared()) {
            throw new DomainException(
                'Only a CLEARED payment can settle a Copy Trade billing invoice.'
            );
        }

        $payment->loadMissing('order');

        if (
            (int) $payment->user_id
            !== (int) $invoice->user_id
        ) {
            throw new DomainException(
                'Payment owner does not match the billing invoice owner.'
            );
        }

        if (
            strtoupper((string) $payment->currency)
            !== strtoupper((string) $invoice->currency)
        ) {
            throw new DomainException(
                'Payment currency does not match the billing invoice.'
            );
        }

        if (
            (int) $payment->gross_amount_minor
            !== (int) $invoice->total_minor
        ) {
            throw new DomainException(
                'Payment amount does not match the billing invoice total.'
            );
        }

        if (
            $payment->order
            && $payment->order->product_code
                !== $invoice->product_code
        ) {
            throw new DomainException(
                'Payment product code does not match the billing invoice.'
            );
        }

        return DB::transaction(
            function () use (
                $invoice,
                $payment,
                $admin
            ): CopyTradeBillingInvoice {
                $locked = CopyTradeBillingInvoice::query()
                    ->whereKey($invoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    ! in_array(
                        $locked->status,
                        [
                            CopyTradeBillingInvoice::STATUS_OPEN,
                            CopyTradeBillingInvoice::STATUS_PAID,
                        ],
                        true
                    )
                ) {
                    throw new DomainException(
                        'Only an OPEN Copy Trade invoice can be newly settled.'
                    );
                }

                if ($locked->isPaid()) {
                    if (
                        (int) $locked->payment_id
                        !== (int) $payment->getKey()
                    ) {
                        throw new DomainException(
                            'This invoice is already settled by another payment.'
                        );
                    }

                    return $locked->fresh([
                        'payment',
                        'paymentOrder',
                        'cycle',
                        'lines',
                    ]);
                }

                $locked->forceFill([
                    'payment_order_id' =>
                        $payment->payment_order_id,
                    'payment_id' =>
                        $payment->getKey(),
                    'status' =>
                        CopyTradeBillingInvoice::STATUS_PAID,
                    'paid_at' =>
                        $payment->cleared_at ?? now(),
                ])->save();

                CopyTradeBillingPaymentEvent::query()
                    ->create([
                        'billing_invoice_id' =>
                            $locked->getKey(),
                        'payment_order_id' =>
                            $payment->payment_order_id,
                        'payment_id' =>
                            $payment->getKey(),
                        'event_type' =>
                            CopyTradeBillingPaymentEvent::EVENT_CLEARED,
                        'evidence' => [
                            'payment_uuid' =>
                                $payment->uuid,
                            'currency' =>
                                $payment->currency,
                            'gross_amount_minor' =>
                                $payment->gross_amount_minor,
                        ],
                    ]);

                $cycle = CopyTradeBillingCycle::query()
                    ->whereKey($locked->billing_cycle_id)
                    ->lockForUpdate()
                    ->first();

                if ($cycle) {
                    $cycle->forceFill([
                        'status' =>
                            CopyTradeBillingCycle::STATUS_PAID,
                        'grace_started_at' => null,
                        'grace_expires_at' => null,
                    ])->save();
                }

                UserEntitlement::query()
                    ->updateOrCreate(
                        [
                            'user_id' =>
                                $locked->user_id,
                            'product_code' =>
                                $locked->product_code,
                        ],
                        [
                            'status' =>
                                UserEntitlement::STATUS_ACTIVE,
                            'activated_at' =>
                                $payment->cleared_at
                                ?? now(),
                            'payment_id' =>
                                $payment->getKey(),
                            'revoked_at' => null,
                            'revoke_reason' => null,
                        ]
                    );

                $account = CopyTradeBrokerAccount::query()
                    ->whereKey($locked->broker_account_id)
                    ->lockForUpdate()
                    ->first();

                if (
                    $account
                    && $account->status
                        === CopyTradeBrokerAccount::STATUS_SUSPENDED
                    && $account->last_status_reason
                        === 'COPY_TRADE_BILLING_UNPAID'
                ) {
                    $account->forceFill([
                        'status' =>
                            CopyTradeBrokerAccount::STATUS_ACTIVE,
                        'suspended_at' => null,
                        'last_status_reason' => null,
                        'status_changed_by_admin_id' =>
                            $admin?->getKey(),
                    ])->save();
                }

                AuditLog::record(
                    actorType:
                        $admin ? 'ADMIN' : 'SYSTEM',
                    actorId:
                        $admin?->getKey(),
                    action:
                        'COPY_TRADE_BILLING_INVOICE_PAID',
                    module: 'COPY_TRADING',
                    targetType:
                        'COPY_TRADE_BILLING_INVOICE',
                    targetId:
                        $locked->getKey(),
                    newValues: [
                        'invoice_uuid' =>
                            $locked->uuid,
                        'payment_uuid' =>
                            $payment->uuid,
                        'currency' =>
                            $payment->currency,
                        'amount_minor' =>
                            $payment->gross_amount_minor,
                    ]
                );

                return $locked->fresh([
                    'payment',
                    'paymentOrder',
                    'cycle',
                    'lines',
                ]);
            },
            3
        );
    }

    public function reconcileReversedPayment(
        CopyTradeBillingInvoice $invoice
    ): CopyTradeBillingInvoice {
        $invoice->loadMissing([
            'payment',
            'cycle',
        ]);

        $payment = $invoice->payment;

        if (
            ! $payment instanceof Payment
            || ! $payment->isPaymentReversed()
        ) {
            throw new DomainException(
                'The linked payment is not reversed.'
            );
        }

        return DB::transaction(
            function () use (
                $invoice,
                $payment
            ): CopyTradeBillingInvoice {
                $locked =
                    CopyTradeBillingInvoice::query()
                        ->whereKey(
                            $invoice->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $locked->status
                    === CopyTradeBillingInvoice::STATUS_PAYMENT_REVERSED
                ) {
                    return $locked->fresh([
                        'payment',
                        'cycle',
                        'lines',
                    ]);
                }

                $locked->forceFill([
                    'status' =>
                        CopyTradeBillingInvoice::STATUS_PAYMENT_REVERSED,
                ])->save();

                CopyTradeBillingPaymentEvent::query()
                    ->create([
                        'billing_invoice_id' =>
                            $locked->getKey(),
                        'payment_order_id' =>
                            $payment->payment_order_id,
                        'payment_id' =>
                            $payment->getKey(),
                        'event_type' =>
                            CopyTradeBillingPaymentEvent::EVENT_REVERSED,
                        'evidence' => [
                            'payment_uuid' =>
                                $payment->uuid,
                            'reversal_code' =>
                                $payment
                                    ->payment_reversal_code,
                            'payment_reversed_at' =>
                                $payment
                                    ->payment_reversed_at
                                    ?->toIso8601String(),
                        ],
                    ]);

                if ($locked->billing_cycle_id !== null) {
                    CopyTradeBillingCycle::query()
                        ->whereKey(
                            $locked->billing_cycle_id
                        )
                        ->lockForUpdate()
                        ->update([
                            'status' =>
                                CopyTradeBillingCycle::STATUS_SUSPENDED,
                        ]);
                }

                UserEntitlement::query()
                    ->where(
                        'user_id',
                        $locked->user_id
                    )
                    ->where(
                        'product_code',
                        $locked->product_code
                    )
                    ->where(
                        'payment_id',
                        $payment->getKey()
                    )
                    ->where(
                        'status',
                        UserEntitlement::STATUS_ACTIVE
                    )
                    ->get()
                    ->each(function (
                        UserEntitlement $entitlement
                    ): void {
                        $entitlement->forceFill([
                            'status' =>
                                UserEntitlement::STATUS_REVOKED,
                            'revoked_at' =>
                                now(),
                            'revoke_reason' =>
                                'COPY_TRADE_BILLING_PAYMENT_REVERSED',
                        ])->save();
                    });

                $account =
                    CopyTradeBrokerAccount::query()
                        ->whereKey(
                            $locked->broker_account_id
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $account
                    && $account->status
                    === CopyTradeBrokerAccount::STATUS_ACTIVE
                ) {
                    $account->forceFill([
                        'status' =>
                            CopyTradeBrokerAccount::STATUS_SUSPENDED,
                        'suspended_at' =>
                            now(),
                        'last_status_reason' =>
                            'COPY_TRADE_BILLING_PAYMENT_REVERSED',
                    ])->save();
                }

                AuditLog::record(
                    actorType: 'SYSTEM',
                    actorId: null,
                    action:
                        'COPY_TRADE_BILLING_PAYMENT_REVERSED',
                    module: 'COPY_TRADING',
                    targetType:
                        'COPY_TRADE_BILLING_INVOICE',
                    targetId:
                        $locked->getKey(),
                    newValues: [
                        'invoice_uuid' =>
                            $locked->uuid,
                        'payment_uuid' =>
                            $payment->uuid,
                        'new_trades_suspended' =>
                            true,
                        'existing_trade_safety_unchanged' =>
                            true,
                    ]
                );

                return $locked->fresh([
                    'payment',
                    'cycle',
                    'lines',
                ]);
            },
            3
        );
    }

}
