<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AffiliateMilestoneReward extends Model
{
    public const STATUS_EARNED = 'EARNED';

    protected $fillable = [
        'uuid',
        'affiliate_user_id',
        'affiliate_stage_id',
        'milestone_sales',
        'gift_amount_cents',
        'currency',
        'status',
        'earned_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reward): void {
            if (blank($reward->uuid)) {
                $reward->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'milestone_sales' => 'integer',
            'gift_amount_cents' => 'integer',
            'earned_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'affiliate_user_id'
        );
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(
            AffiliateStage::class,
            'affiliate_stage_id'
        );
    }
}
