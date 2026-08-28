<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeVerifiedBalanceSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'broker_account_id',
        'currency',
        'verified_billable_balance_minor',
        'source',
        'source_reference_hash',
        'source_reference',
        'observed_at',
        'recorded_by_admin_id',
    ];

    protected $hidden = [
        'source_reference_hash',
        'source_reference',
    ];

    protected function casts(): array
    {
        return [
            'verified_billable_balance_minor' => 'integer',
            'source_reference' => 'encrypted',
            'observed_at' => 'datetime',
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
                'Verified Billable Balance snapshots are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Verified Billable Balance snapshots cannot be deleted through Eloquent.'
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

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'recorded_by_admin_id'
        );
    }
}
