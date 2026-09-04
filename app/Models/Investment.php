<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Investment extends Model
{
    public const PROFIT_MODEL_PERFORMANCE_SHARE = 'PERFORMANCE_SHARE';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_AWAITING_DEPOSIT = 'AWAITING_DEPOSIT';
    public const STATUS_DEPOSIT_VERIFICATION = 'DEPOSIT_VERIFICATION';
    public const STATUS_AWAITING_RISK_ACCEPTANCE = 'AWAITING_RISK_ACCEPTANCE';
    public const STATUS_AWAITING_AGREEMENT = 'AWAITING_AGREEMENT';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EARLY_TERMINATION_REVIEW = 'EARLY_TERMINATION_REVIEW';
    public const STATUS_EARLY_TERMINATED = 'EARLY_TERMINATED';
    public const STATUS_MATURED = 'MATURED';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const SETTLEMENT_ON_MATURITY = 'ON_MATURITY';
    public const SETTLEMENT_CUSTOM_DATE = 'CUSTOM_DATE';

    public const PRINCIPAL_NOT_DUE = 'NOT_DUE';
    public const PRINCIPAL_DUE = 'DUE';
    public const PRINCIPAL_PAYMENT_PREPARED = 'PAYMENT_PREPARED';
    public const PRINCIPAL_APPROVED = 'APPROVED';
    public const PRINCIPAL_SETTLED = 'SETTLED';
    public const PRINCIPAL_ON_HOLD = 'ON_HOLD';

    protected $fillable = [
        'uuid',
        'investment_code',
        'user_id',
        'investment_product_id',
        'status',
        'profit_model',
        'deposit_amount',
        'deposit_currency',
        'deposit_network',
        'investment_period_years',
        'profit_review_interval_months',
        'customer_profit_share_bps',
        'company_profit_share_bps',
        'early_exit_return_bps',
        'fixed_return_guaranteed',
        'settlement_mode',
        'custom_settlement_date',
        'start_date',
        'maturity_date',
        'principal_settlement_date',
        'principal_settlement_status',
        'payout_wallet_address',
        'payout_wallet_network',
        'payout_wallet_verified_at',
        'payout_wallet_verified_by_admin_id',
        'high_water_mark_usdt',
        'loss_carryforward_usdt',
        'activated_at',
        'closed_at',
        'created_by_admin_id',
        'updated_by_admin_id',
        'activated_by_admin_id',
    ];

    protected $hidden = [
        'payout_wallet_address',
    ];

    protected function casts(): array
    {
        return [
            'deposit_amount' => 'decimal:8',
            'customer_profit_share_bps' => 'integer',
            'company_profit_share_bps' => 'integer',
            'early_exit_return_bps' => 'integer',
            'fixed_return_guaranteed' => 'boolean',
            'custom_settlement_date' => 'date',
            'start_date' => 'date',
            'maturity_date' => 'date',
            'principal_settlement_date' => 'date',
            'payout_wallet_address' => 'encrypted',
            'payout_wallet_verified_at' => 'datetime',
            'high_water_mark_usdt' => 'decimal:8',
            'loss_carryforward_usdt' => 'decimal:8',
            'activated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(
            InvestmentProduct::class,
            'investment_product_id'
        );
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(InvestmentAgreement::class);
    }

    public function riskAcceptances(): HasMany
    {
        return $this->hasMany(InvestmentRiskAcceptance::class);
    }

    public function profitPeriods(): HasMany
    {
        return $this->hasMany(InvestmentProfitPeriod::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    public function assetAllocations(): HasMany
    {
        return $this->hasMany(InvestmentAssetAllocation::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(InvestmentSettlement::class);
    }

    public function latestSettlement(): HasOne
    {
        return $this->hasOne(InvestmentSettlement::class)->latestOfMany();
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function activatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'activated_by_admin_id'
        );
    }

    public function latestAgreement()
    {
        return $this->hasOne(InvestmentAgreement::class)->latestOfMany('version');
    }

    public function customerProfitSharePercent(): float
    {
        return $this->customer_profit_share_bps / 100;
    }

    public function companyProfitSharePercent(): float
    {
        return $this->company_profit_share_bps / 100;
    }

    public function earlyExitReturnPercent(): float
    {
        return $this->early_exit_return_bps / 100;
    }
}
