<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestmentSettlement extends Model
{
    public const TYPE_MATURITY = 'MATURITY';
    public const TYPE_EARLY_TERMINATION = 'EARLY_TERMINATION';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PREPARED = 'PREPARED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_SETTLED = 'SETTLED';
    public const STATUS_ON_HOLD = 'ON_HOLD';

    public const CALCULATION_VERSION = 'TERMINAL-SETTLEMENT-v1';

    protected $fillable = [
        'uuid',
        'investment_id',
        'type',
        'status',
        'cutoff_at',
        'due_at',
        'return_bps',
        'reason',
        'calculation_version',
        'calculation_source_sha256',
        'calculation_snapshot',
        'reference_capital_usdt',
        'gross_realized_profit_usdt',
        'realized_losses_usdt',
        'approved_costs_usdt',
        'loss_carryforward_open_usdt',
        'loss_carryforward_close_usdt',
        'eligible_net_profit_usdt',
        'customer_profit_share_usdt',
        'company_profit_share_usdt',
        'rounding_adjustment_usdt',
        'prepared_by_admin_id',
        'prepared_at',
        'approved_by_admin_id',
        'approved_at',
        'settled_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'due_at' => 'date',
            'return_bps' => 'integer',
            'calculation_snapshot' => 'array',
            'reference_capital_usdt' => 'decimal:8',
            'gross_realized_profit_usdt' => 'decimal:8',
            'realized_losses_usdt' => 'decimal:8',
            'approved_costs_usdt' => 'decimal:8',
            'loss_carryforward_open_usdt' => 'decimal:8',
            'loss_carryforward_close_usdt' => 'decimal:8',
            'eligible_net_profit_usdt' => 'decimal:8',
            'customer_profit_share_usdt' => 'decimal:8',
            'company_profit_share_usdt' => 'decimal:8',
            'rounding_adjustment_usdt' => 'decimal:8',
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(InvestmentSettlementAsset::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class, 'investment_settlement_id');
    }

    public function preparedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'prepared_by_admin_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'approved_by_admin_id');
    }
}
