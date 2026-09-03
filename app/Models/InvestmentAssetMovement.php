<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentAssetMovement extends Model
{
    public const TYPE_BUY = 'BUY';
    public const TYPE_SELL = 'SELL';
    public const TYPE_REVERSAL = 'REVERSAL';

    protected $fillable = [
        'investment_id',
        'investment_trade_id',
        'investment_financial_adjustment_id',
        'investment_asset_allocation_id',
        'movement_type',
        'units_delta',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'units_delta' => 'decimal:18',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(InvestmentTrade::class, 'investment_trade_id');
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentFinancialAdjustment::class,
            'investment_financial_adjustment_id'
        );
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentAssetAllocation::class,
            'investment_asset_allocation_id'
        );
    }
}
