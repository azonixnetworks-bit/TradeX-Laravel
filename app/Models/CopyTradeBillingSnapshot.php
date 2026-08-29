<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingSnapshot extends Model
{
    public const UPDATED_AT = null;

    public const PURPOSE_INITIAL = 'INITIAL';
    public const PURPOSE_RENEWAL = 'RENEWAL';
    public const PURPOSE_UPGRADE = 'UPGRADE';
    public const PURPOSE_BASELINE = 'BASELINE';

    protected $fillable = [
        'uuid',
        'idempotency_key_hash',
        'broker_account_id',
        'billing_cycle_id',
        'source_balance_snapshot_id',
        'billing_balance_event_id',
        'purpose',
        'market_category',
        'currency',
        'verified_billable_balance_minor',
        'billing_eligible_balance_minor',
        'tier_code',
        'full_monthly_fee_minor',
        'event_at',
        'period_start',
        'period_end',
        'ratio_numerator',
        'ratio_denominator',
        'formula_code',
        'calculation_inputs',
        'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_billable_balance_minor' => 'integer',
            'billing_eligible_balance_minor' => 'integer',
            'full_monthly_fee_minor' => 'integer',
            'event_at' => 'datetime',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'ratio_numerator' => 'integer',
            'ratio_denominator' => 'integer',
            'calculation_inputs' => 'array',
            'locked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            if (blank($snapshot->uuid)) {
                $snapshot->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade billing snapshots are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing snapshots cannot be deleted through Eloquent.'
            );
        });
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
        );
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBillingCycle::class,
            'billing_cycle_id'
        );
    }

    public function sourceBalanceSnapshot(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeVerifiedBalanceSnapshot::class,
            'source_balance_snapshot_id'
        );
    }

    public function balanceEvent(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBillingBalanceEvent::class,
            'billing_balance_event_id'
        );
    }
}
