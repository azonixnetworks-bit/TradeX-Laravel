<?php

namespace App\Services\CopyTrading;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CopyTradeBrokerAccount;
use App\Models\CopyTradeVerifiedBalanceSnapshot;
use DomainException;
use Illuminate\Support\Carbon;

class CopyTradeVerifiedBalanceService
{
    public function record(
        CopyTradeBrokerAccount $account,
        int $verifiedBillableBalanceMinor,
        string $currency,
        string $source,
        ?string $sourceReference = null,
        ?AdminUser $admin = null,
        ?Carbon $observedAt = null
    ): CopyTradeVerifiedBalanceSnapshot {
        if (! $account->hasVerifiedBrokerRelationship()) {
            throw new DomainException(
                'Verified Billable Balance can only be recorded for a verified broker relationship.'
            );
        }

        if ($verifiedBillableBalanceMinor < 0) {
            throw new DomainException(
                'Verified Billable Balance cannot be negative.'
            );
        }

        $normalizedCurrency = strtoupper(trim($currency));
        $requiredCurrency = strtoupper(
            (string) config(
                'tradex_copy_trading.activation.currency',
                'USD'
            )
        );

        if ($normalizedCurrency !== $requiredCurrency) {
            throw new DomainException(
                'Verified Billable Balance currency must be '.$requiredCurrency.'.'
            );
        }

        $normalizedSource = strtoupper(trim($source));
        $allowedSources = (array) config(
            'tradex_copy_trading.activation.balance_sources',
            []
        );

        if (! in_array($normalizedSource, $allowedSources, true)) {
            throw new DomainException(
                'Unsupported Verified Billable Balance source.'
            );
        }

        $reference = filled($sourceReference)
            ? trim((string) $sourceReference)
            : null;

        $snapshot = CopyTradeVerifiedBalanceSnapshot::query()
            ->create([
                'broker_account_id' => $account->getKey(),
                'currency' => $requiredCurrency,
                'verified_billable_balance_minor' =>
                    $verifiedBillableBalanceMinor,
                'source' => $normalizedSource,
                'source_reference_hash' =>
                    $reference !== null
                        ? hash('sha256', $reference)
                        : null,
                'source_reference' => $reference,
                'observed_at' => $observedAt ?? now(),
                'recorded_by_admin_id' =>
                    $admin?->getKey(),
            ]);

        AuditLog::record(
            actorType:
                $admin instanceof AdminUser
                    ? 'ADMIN'
                    : 'SYSTEM',
            actorId:
                $admin?->getKey(),
            action:
                'COPY_TRADE_VERIFIED_BILLABLE_BALANCE_RECORDED',
            module:
                'COPY_TRADING',
            targetType:
                'COPY_TRADE_BROKER_ACCOUNT',
            targetId:
                $account->getKey(),
            newValues: [
                'snapshot_uuid' =>
                    $snapshot->uuid,
                'currency' =>
                    $snapshot->currency,
                'verified_billable_balance_minor' =>
                    $snapshot->verified_billable_balance_minor,
                'source' =>
                    $snapshot->source,
                'source_reference_recorded' =>
                    $reference !== null,
                'observed_at' =>
                    $snapshot->observed_at?->toIso8601String(),
            ]
        );

        return $snapshot;
    }
}
