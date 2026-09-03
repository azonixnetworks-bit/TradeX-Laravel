<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentFinancialAdjustment extends Model
{
    public const TARGET_TRADE = 'TRADE';
    public const TARGET_COST = 'COST';

    public const TYPE_REVERSAL = 'REVERSAL';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const STATUS_PENDING = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'investment_profit_period_id',
        'target_type',
        'target_id',
        'adjustment_type',
        'realized_pnl_delta_usdt',
        'approved_cost_delta_usdt',
        'reason',
        'status',
        'recorded_by_admin_id',
        'verified_by_admin_id',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'realized_pnl_delta_usdt' => 'decimal:8',
            'approved_cost_delta_usdt' => 'decimal:8',
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
}
