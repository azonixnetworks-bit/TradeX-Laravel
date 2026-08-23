<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliateCommission extends Model
{
    public const RATE_SOURCE_STANDARD = 'STANDARD';
    public const RATE_SOURCE_SPECIAL = 'SPECIAL';
    public const STATUS_EARNED = 'EARNED';

    protected $fillable = [
        'uuid',
        'affiliate_qualified_sale_id',
        'affiliate_user_id',
        'qualified_ordinal',
        'rate_bps',
        'rate_source',
        'calculation_base_cents',
        'commission_amount_cents',
        'currency',
        'status',
        'earned_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $commission): void {
            if (blank($commission->uuid)) {
                $commission->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'qualified_ordinal' => 'integer',
            'rate_bps' => 'integer',
            'calculation_base_cents' => 'integer',
            'commission_amount_cents' => 'integer',
            'earned_at' => 'datetime',
        ];
    }

    public function qualifiedSale(): BelongsTo
    {
        return $this->belongsTo(
            AffiliateQualifiedSale::class,
            'affiliate_qualified_sale_id'
        );
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'affiliate_user_id'
        );
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(
            AffiliateCommissionAdjustment::class
        );
    }

    public function netAmountCents(): int
    {
        return (int) $this->commission_amount_cents
            + (int) $this->adjustments()->sum('amount_cents');
    }
}
