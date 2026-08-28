<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeEntitlementPeriod extends Model
{
    public const UPDATED_AT = null;

    public const TYPE_LIFETIME_FREE =
        'LIFETIME_FREE';

    public const TYPE_THIRTY_DAY_FREE =
        'THIRTY_DAY_FREE';

    public const TYPE_PAID =
        'PAID';

    protected $fillable = [
        'uuid',
        'broker_account_id',
        'account_benefit_id',
        'user_entitlement_id',
        'entitlement_type',
        'product_code',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $period): void {
            if (blank($period->uuid)) {
                $period->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade entitlement periods are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade entitlement periods cannot be deleted through Eloquent.'
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

    public function accountBenefit(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeAccountBenefit::class,
            'account_benefit_id'
        );
    }

    public function userEntitlement(): BelongsTo
    {
        return $this->belongsTo(
            UserEntitlement::class,
            'user_entitlement_id'
        );
    }
}
