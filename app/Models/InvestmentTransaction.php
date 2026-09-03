<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentTransaction extends Model
{
    public const DIRECTION_IN = 'IN';
    public const DIRECTION_OUT = 'OUT';

    public const TYPE_INVESTMENT_DEPOSIT = 'INVESTMENT_DEPOSIT';
    public const TYPE_PROFIT_PAYOUT = 'PROFIT_PAYOUT';
    public const TYPE_PRINCIPAL_RETURN = 'PRINCIPAL_RETURN';
    public const TYPE_EARLY_EXIT_IN_KIND = 'EARLY_EXIT_IN_KIND';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'investment_profit_period_id',
        'direction',
        'type',
        'asset_symbol',
        'amount',
        'usdt_equivalent',
        'network',
        'from_address',
        'to_address',
        'txid',
        'status',
        'occurred_at',
        'recorded_by_admin_id',
        'verified_by_admin_id',
        'verified_at',
        'notes',
    ];

    protected $hidden = [
        'from_address',
        'to_address',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'usdt_equivalent' => 'decimal:8',
            'from_address' => 'encrypted',
            'to_address' => 'encrypted',
            'occurred_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function profitPeriod(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentProfitPeriod::class,
            'investment_profit_period_id'
        );
    }

    public function recordedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'recorded_by_admin_id'
        );
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'verified_by_admin_id'
        );
    }
}
