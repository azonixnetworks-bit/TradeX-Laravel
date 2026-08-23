<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateProfile extends Model
{
    protected $fillable = [
        'user_id',
        'current_stage_id',
        'qualified_sales_count',
        'lifetime_cleared_sales_count',
        'qualified_net_eligible_revenue_cents',
        'lifetime_commission_earned_cents',
        'commission_adjustment_cents',
        'milestone_gifts_earned_cents',
        'special_commission_bps',
        'special_commission_approved_at',
        'special_commission_approved_by_admin_id',
        'special_commission_reason',
    ];

    protected function casts(): array
    {
        return [
            'qualified_sales_count' => 'integer',
            'lifetime_cleared_sales_count' => 'integer',
            'qualified_net_eligible_revenue_cents' => 'integer',
            'lifetime_commission_earned_cents' => 'integer',
            'commission_adjustment_cents' => 'integer',
            'milestone_gifts_earned_cents' => 'integer',
            'special_commission_bps' => 'integer',
            'special_commission_approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentStage(): BelongsTo
    {
        return $this->belongsTo(
            AffiliateStage::class,
            'current_stage_id'
        );
    }

    public function specialCommissionApprovedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'special_commission_approved_by_admin_id'
        );
    }

    public function qualifiedSales(): HasMany
    {
        return $this->hasMany(
            AffiliateQualifiedSale::class,
            'affiliate_user_id',
            'user_id'
        );
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(
            AffiliateCommission::class,
            'affiliate_user_id',
            'user_id'
        );
    }

    public function milestoneRewards(): HasMany
    {
        return $this->hasMany(
            AffiliateMilestoneReward::class,
            'affiliate_user_id',
            'user_id'
        );
    }

    public function hasApprovedSpecialCommission(): bool
    {
        return $this->special_commission_bps !== null
            && $this->special_commission_approved_at !== null;
    }

    public function netCommissionCents(): int
    {
        return (int) $this->lifetime_commission_earned_cents
            + (int) $this->commission_adjustment_cents;
    }
}
