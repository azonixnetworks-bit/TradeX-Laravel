<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateStage extends Model
{
    public const STARTER = 'STARTER';
    public const GROWTH = 'GROWTH';
    public const PRO = 'PRO';
    public const ELITE = 'ELITE';
    public const DIAMOND = 'DIAMOND';
    public const AMBASSADOR = 'AMBASSADOR';
    public const BUSINESS_PARTNER = 'BUSINESS_PARTNER';

    protected $fillable = [
        'code',
        'name',
        'min_qualified_sales',
        'max_qualified_sales',
        'standard_commission_bps',
        'milestone_sales',
        'milestone_gift_cents',
        'cumulative_gift_cents',
        'commercial_rule',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_qualified_sales' => 'integer',
            'max_qualified_sales' => 'integer',
            'standard_commission_bps' => 'integer',
            'milestone_sales' => 'integer',
            'milestone_gift_cents' => 'integer',
            'cumulative_gift_cents' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(
            AffiliateProfile::class,
            'current_stage_id'
        );
    }

    public function milestoneRewards(): HasMany
    {
        return $this->hasMany(AffiliateMilestoneReward::class);
    }

    public static function forQualifiedSalesCount(int $count): ?self
    {
        if ($count < 1) {
            return null;
        }

        return self::query()
            ->active()
            ->where('min_qualified_sales', '<=', $count)
            ->where(function (Builder $query) use ($count): void {
                $query
                    ->whereNull('max_qualified_sales')
                    ->orWhere('max_qualified_sales', '>=', $count);
            })
            ->orderByDesc('min_qualified_sales')
            ->first();
    }
}
