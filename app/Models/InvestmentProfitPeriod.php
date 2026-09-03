<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentProfitPeriod extends Model
{
    public const STATUS_UPCOMING = 'UPCOMING';
    public const STATUS_DUE = 'DUE';
    public const STATUS_PREPARED = 'PREPARED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PAID = 'PAID';
    public const STATUS_NO_PROFIT = 'NO_PROFIT';
    public const STATUS_ON_HOLD = 'ON_HOLD';

    // ADMIN-24 Phase 3 workflow states.
    public const STATUS_APPROVAL_PENDING = 'APPROVAL_PENDING';
    public const STATUS_PAYMENT_RECORDED = 'PAYMENT_RECORDED';
    public const STATUS_SETTLED = 'SETTLED';
    public const STATUS_NO_DISTRIBUTION = 'NO_DISTRIBUTION';
    public const STATUS_LOSS_CARRIED_FORWARD = 'LOSS_CARRIED_FORWARD';

    public const OUTCOME_PAYABLE = 'PAYABLE';
    public const OUTCOME_NO_DISTRIBUTION = 'NO_DISTRIBUTION';
    public const OUTCOME_LOSS_CARRIED_FORWARD = 'LOSS_CARRIED_FORWARD';

    protected $fillable = [
        'investment_id',
        'sequence',
        'period_start',
        'period_end',
        'status',
        'distribution_outcome',
        'opening_equity_usdt',
        'closing_equity_usdt',
        'gross_realized_profit_usdt',
        'realized_losses_usdt',
        'approved_costs_usdt',
        'loss_carryforward_open_usdt',
        'loss_carryforward_close_usdt',
        'high_water_mark_open_usdt',
        'high_water_mark_close_usdt',
        'eligible_net_profit_usdt',
        'customer_profit_share_usdt',
        'company_profit_share_usdt',
        'rounding_adjustment_usdt',
        'calculation_version',
        'calculation_source_sha256',
        'calculation_snapshot',
        'prepared_by_admin_id',
        'approved_by_admin_id',
        'prepared_at',
        'approved_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'opening_equity_usdt' => 'decimal:8',
            'closing_equity_usdt' => 'decimal:8',
            'gross_realized_profit_usdt' => 'decimal:8',
            'realized_losses_usdt' => 'decimal:8',
            'approved_costs_usdt' => 'decimal:8',
            'loss_carryforward_open_usdt' => 'decimal:8',
            'loss_carryforward_close_usdt' => 'decimal:8',
            'high_water_mark_open_usdt' => 'decimal:8',
            'high_water_mark_close_usdt' => 'decimal:8',
            'eligible_net_profit_usdt' => 'decimal:8',
            'customer_profit_share_usdt' => 'decimal:8',
            'company_profit_share_usdt' => 'decimal:8',
            'rounding_adjustment_usdt' => 'decimal:8',
            'calculation_snapshot' => 'array',
            'prepared_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
