<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingCycle extends Model
{
    public const STATUS_FREE = 'FREE';
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PAID = 'PAID';
    public const STATUS_GRACE = 'GRACE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_INELIGIBLE = 'INELIGIBLE';

    protected $fillable = [
        'uuid',
        'broker_account_id',
        'market_category',
        'cycle_basis',
        'period_start',
        'period_end',
        'effective_start_at',
        'currency',
        'active_tier_code',
        'active_full_fee_minor',
        'status',
        'grace_started_at',
        'grace_expires_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'effective_start_at' => 'datetime',
            'active_full_fee_minor' => 'integer',
            'grace_started_at' => 'datetime',
            'grace_expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $cycle): void {
            if (blank($cycle->uuid)) {
                $cycle->uuid = (string) Str::uuid();
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing cycles cannot be deleted through Eloquent.'
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

    public function snapshots(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingSnapshot::class,
            'billing_cycle_id'
        )->latest('event_at');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingInvoice::class,
            'billing_cycle_id'
        )->latest('issued_at');
    }
}
