<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentTrade extends Model
{
    public const ACTION_BUY = 'BUY';
    public const ACTION_SELL = 'SELL';

    public const INSTRUMENT_SPOT = 'SPOT';

    public const STATUS_PENDING = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'investment_profit_period_id',
        'action',
        'instrument_type',
        'asset_name',
        'asset_symbol',
        'network',
        'contract_address',
        'exchange_broker',
        'quantity',
        'executed_price_usdt',
        'gross_value_usdt',
        'fee_usdt',
        'realized_pnl_usdt',
        'order_reference',
        'executed_at',
        'status',
        'recorded_by_admin_id',
        'verified_by_admin_id',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:18',
            'executed_price_usdt' => 'decimal:8',
            'gross_value_usdt' => 'decimal:8',
            'fee_usdt' => 'decimal:8',
            'realized_pnl_usdt' => 'decimal:8',
            'executed_at' => 'datetime',
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
        return $this->belongsTo(AdminUser::class, 'recorded_by_admin_id');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'verified_by_admin_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(InvestmentEvidence::class, 'investment_trade_id');
    }

    public function assetMovements(): HasMany
    {
        return $this->hasMany(InvestmentAssetMovement::class, 'investment_trade_id');
    }

    public function approvedCosts(): HasMany
    {
        return $this->hasMany(InvestmentApprovedCost::class, 'investment_trade_id');
    }
}
