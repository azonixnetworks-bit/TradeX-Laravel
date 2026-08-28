<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeAccountBenefit extends Model
{
    public const UPDATED_AT = null;

    public const BENEFIT_LIFETIME_FREE_ACCOUNT_1 =
        'LIFETIME_FREE_ACCOUNT_1';

    public const BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1 =
        'THIRTY_DAY_FREE_ACCOUNT_1';

    public const BENEFIT_PAID_REQUIRED =
        'PAID_REQUIRED';

    public const VALID_BENEFIT_TYPES = [
        self::BENEFIT_LIFETIME_FREE_ACCOUNT_1,
        self::BENEFIT_THIRTY_DAY_FREE_ACCOUNT_1,
        self::BENEFIT_PAID_REQUIRED,
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'broker_account_id',
        'education_entitlement_id',
        'market_category',
        'account_slot',
        'cohort_position',
        'benefit_type',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'account_slot' => 'integer',
            'cohort_position' => 'integer',
            'assigned_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $benefit): void {
            if (blank($benefit->uuid)) {
                $benefit->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade account benefit assignments are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade account benefit assignments cannot be deleted through Eloquent.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
        );
    }

    public function educationEntitlement(): BelongsTo
    {
        return $this->belongsTo(
            UserEntitlement::class,
            'education_entitlement_id'
        );
    }
}
