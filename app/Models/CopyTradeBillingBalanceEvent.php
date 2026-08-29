<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingBalanceEvent extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_EXTERNAL_DEPOSIT =
        'EXTERNAL_DEPOSIT';

    protected $fillable = [
        'uuid',
        'broker_account_id',
        'balance_snapshot_id',
        'event_type',
        'deposit_amount_minor',
        'source',
        'source_reference',
        'occurred_at',
        'recorded_by_admin_id',
    ];

    protected $hidden = [
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'deposit_amount_minor' =>
                'integer',
            'source_reference' =>
                'encrypted',
            'occurred_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->uuid)) {
                $event->uuid =
                    (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade billing balance events are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing balance events cannot be deleted through Eloquent.'
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

    public function balanceSnapshot(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeVerifiedBalanceSnapshot::class,
            'balance_snapshot_id'
        );
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'recorded_by_admin_id'
        );
    }
}
