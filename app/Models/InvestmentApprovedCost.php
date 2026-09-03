<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentApprovedCost extends Model
{
    public const TYPE_BROKER_COMMISSION = 'BROKER_COMMISSION';
    public const TYPE_EXCHANGE_TRADING_FEE = 'EXCHANGE_TRADING_FEE';
    public const TYPE_BLOCKCHAIN_NETWORK_FEE = 'BLOCKCHAIN_NETWORK_FEE';
    public const TYPE_DIRECT_CUSTODY_TRANSACTION_FEE = 'DIRECT_CUSTODY_TRANSACTION_FEE';

    public const STATUS_PENDING = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'investment_profit_period_id',
        'investment_trade_id',
        'cost_type',
        'amount_usdt',
        'occurred_at',
        'source_reference',
        'notes',
        'status',
        'recorded_by_admin_id',
        'verified_by_admin_id',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount_usdt' => 'decimal:8',
            'occurred_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public static function allowedTypes(): array
    {
        return [
            self::TYPE_BROKER_COMMISSION,
            self::TYPE_EXCHANGE_TRADING_FEE,
            self::TYPE_BLOCKCHAIN_NETWORK_FEE,
            self::TYPE_DIRECT_CUSTODY_TRANSACTION_FEE,
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

    public function trade(): BelongsTo
    {
        return $this->belongsTo(InvestmentTrade::class, 'investment_trade_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(InvestmentEvidence::class, 'investment_approved_cost_id');
    }
}
