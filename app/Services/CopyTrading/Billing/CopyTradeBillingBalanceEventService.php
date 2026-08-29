<?php

namespace App\Services\CopyTrading\Billing;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBillingBalanceEvent;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeVerifiedBalanceSnapshot;
use DomainException;

class CopyTradeBillingBalanceEventService
{
    public function recordExternalDeposit(
        CopyTradeBrokerAccount $account,
        CopyTradeVerifiedBalanceSnapshot $snapshot,
        int $depositAmountMinor,
        ?string $sourceReference = null,
        ?AdminUser $admin = null
    ): CopyTradeBillingBalanceEvent {
        if (! $account->isActive()) {
            throw new DomainException(
                'External-deposit billing evidence can only be recorded for an ACTIVE Copy Trade account.'
            );
        }

        if (
            (int) $snapshot->broker_account_id
            !== (int) $account->getKey()
        ) {
            throw new DomainException(
                'Verified Billable Balance snapshot does not belong to this broker account.'
            );
        }

        if ($depositAmountMinor <= 0) {
            throw new DomainException(
                'External deposit amount must be greater than zero.'
            );
        }

        $existing =
            CopyTradeBillingBalanceEvent::query()
                ->where(
                    'balance_snapshot_id',
                    $snapshot->getKey()
                )
                ->first();

        if ($existing) {
            if (
                $existing->event_type
                !== CopyTradeBillingBalanceEvent::TYPE_EXTERNAL_DEPOSIT
            ) {
                throw new DomainException(
                    'This balance snapshot is already classified by another billing event.'
                );
            }

            if (
                (int) $existing->deposit_amount_minor
                !== $depositAmountMinor
            ) {
                throw new DomainException(
                    'This balance snapshot already has immutable external-deposit evidence with a different amount.'
                );
            }

            return $existing;
        }

        $reference = filled($sourceReference)
            ? trim((string) $sourceReference)
            : null;

        $event =
            CopyTradeBillingBalanceEvent::query()
                ->create([
                    'broker_account_id' =>
                        $account->getKey(),
                    'balance_snapshot_id' =>
                        $snapshot->getKey(),
                    'event_type' =>
                        CopyTradeBillingBalanceEvent::TYPE_EXTERNAL_DEPOSIT,
                    'deposit_amount_minor' =>
                        $depositAmountMinor,
                    'source' =>
                        $snapshot->source,
                    'source_reference' =>
                        $reference,
                    'occurred_at' =>
                        $snapshot->observed_at,
                    'recorded_by_admin_id' =>
                        $admin?->getKey(),
                ]);

        AuditLog::record(
            actorType:
                $admin ? 'ADMIN' : 'SYSTEM',
            actorId:
                $admin?->getKey(),
            action:
                'COPY_TRADE_EXTERNAL_DEPOSIT_BILLING_EVENT_RECORDED',
            module: 'COPY_TRADING',
            targetType:
                'COPY_TRADE_BILLING_BALANCE_EVENT',
            targetId:
                $event->getKey(),
            newValues: [
                'event_uuid' =>
                    $event->uuid,
                'broker_account_uuid' =>
                    $account->uuid,
                'balance_snapshot_uuid' =>
                    $snapshot->uuid,
                'event_type' =>
                    $event->event_type,
                'deposit_amount_minor' =>
                    $event->deposit_amount_minor,
                'source' =>
                    $event->source,
                'source_reference_recorded' =>
                    $reference !== null,
                'occurred_at' =>
                    $event
                        ->occurred_at
                        ?->toIso8601String(),
            ]
        );

        return $event;
    }
}
