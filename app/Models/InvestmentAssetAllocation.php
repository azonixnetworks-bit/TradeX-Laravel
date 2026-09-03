<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentAssetAllocation extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DEPLETED = 'DEPLETED';
    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'investment_id',
        'source_transaction_id',
        'source_trade_id',
        'asset_symbol',
        'network',
        'contract_address',
        'original_units',
        'remaining_units',
        'allocated_cost_usdt',
        'acquired_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'original_units' => 'decimal:18',
            'remaining_units' => 'decimal:18',
            'allocated_cost_usdt' => 'decimal:8',
            'acquired_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function sourceTransaction(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentTransaction::class,
            'source_transaction_id'
        );
    }
}
