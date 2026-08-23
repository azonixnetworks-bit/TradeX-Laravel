<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class AffiliateQualifiedSale extends Model
{
    public const PROVIDER_PAYPAL = 'PAYPAL';
    public const STATUS_CLEARED = 'CLEARED';
    public const STATUS_PAYMENT_REVERSED = 'PAYMENT_REVERSED';

    public const REVERSAL_PAYPAL_CHARGEBACK = 'PAYPAL_CHARGEBACK';
    public const REVERSAL_PAYPAL_PROCESSOR = 'PAYPAL_PROCESSOR_REVERSAL';
    public const REVERSAL_DUPLICATE_PAYMENT = 'DUPLICATE_PAYMENT_CORRECTION';
    public const REVERSAL_UNAUTHORIZED_PAYMENT = 'UNAUTHORIZED_PAYMENT';
    public const REVERSAL_BILLING_ERROR = 'TRADEX_BILLING_ERROR';
    public const REVERSAL_LEGAL_REQUIRED = 'LEGAL_REQUIRED_REVERSAL';

    public const PAYMENT_REVERSAL_CODES = [
        self::REVERSAL_PAYPAL_CHARGEBACK,
        self::REVERSAL_PAYPAL_PROCESSOR,
        self::REVERSAL_DUPLICATE_PAYMENT,
        self::REVERSAL_UNAUTHORIZED_PAYMENT,
        self::REVERSAL_BILLING_ERROR,
        self::REVERSAL_LEGAL_REQUIRED,
    ];

    protected $fillable = [
        'uuid',
        'affiliate_user_id',
        'referred_user_id',
        'source_provider',
        'source_reference',
        'currency',
        'ledger_sequence',
        'qualified_ordinal',
        'net_eligible_revenue_cents',
        'status',
        'qualified_at',
        'payment_reversed_at',
        'payment_reversal_code',
        'payment_reversal_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            if (blank($sale->uuid)) {
                $sale->uuid = (string) Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'ledger_sequence' => 'integer',
            'qualified_ordinal' => 'integer',
            'net_eligible_revenue_cents' => 'integer',
            'qualified_at' => 'datetime',
            'payment_reversed_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'affiliate_user_id'
        );
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'referred_user_id'
        );
    }

    public function commission(): HasOne
    {
        return $this->hasOne(
            AffiliateCommission::class,
            'affiliate_qualified_sale_id'
        );
    }

    public function isCleared(): bool
    {
        return $this->status === self::STATUS_CLEARED;
    }

    public function isPaymentReversed(): bool
    {
        return $this->status === self::STATUS_PAYMENT_REVERSED;
    }
}
