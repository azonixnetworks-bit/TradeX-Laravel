<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AffiliateCommissionAdjustment extends Model
{
    public const TYPE_PAYMENT_REVERSAL = 'PAYMENT_REVERSAL';

    protected $fillable = [
        'uuid',
        'affiliate_commission_id',
        'affiliate_qualified_sale_id',
        'affiliate_user_id',
        'type',
        'amount_cents',
        'currency',
        'reason',
        'created_by_admin_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (blank($adjustment->uuid)) {
                $adjustment->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
        ];
    }

    public function commission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class);
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

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }
}
