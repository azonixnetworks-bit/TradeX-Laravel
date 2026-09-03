<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentEvidence extends Model
{
    protected $table = 'investment_evidences'; // ADMIN-24-PHASE3-V3

    public const TYPE_TRADE_SCREENSHOT = 'TRADE_SCREENSHOT';
    public const TYPE_PNL_STATEMENT = 'PNL_STATEMENT';
    public const TYPE_TERMINAL_PNL_STATEMENT = 'TERMINAL_PNL_STATEMENT';
    public const TYPE_COST_DOCUMENT = 'COST_DOCUMENT';

    public const STATUS_PENDING = 'PENDING_VERIFICATION';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'investment_id',
        'investment_profit_period_id',
        'investment_settlement_id',
        'investment_trade_id',
        'investment_approved_cost_id',
        'evidence_type',
        'account_reference',
        'source_reference',
        'period_start',
        'period_end',
        'document_disk',
        'document_path',
        'document_mime_type',
        'document_size_bytes',
        'document_sha256',
        'status',
        'uploaded_by_admin_id',
        'verified_by_admin_id',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'document_size_bytes' => 'integer',
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

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentSettlement::class,
            'investment_settlement_id'
        );
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(InvestmentTrade::class, 'investment_trade_id');
    }

    public function approvedCost(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentApprovedCost::class,
            'investment_approved_cost_id'
        );
    }

    public function uploadedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'uploaded_by_admin_id');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'verified_by_admin_id');
    }
}
