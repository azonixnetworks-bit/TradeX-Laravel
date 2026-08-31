<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Payment extends Model
{
    public const STATUS_CLEARED = 'CLEARED';
    public const STATUS_PAYMENT_REVERSED = 'PAYMENT_REVERSED';

    public const AFFILIATE_NOT_APPLICABLE = 'NOT_APPLICABLE';
    public const AFFILIATE_PENDING = 'PENDING';
    public const AFFILIATE_PROCESSED = 'PROCESSED';
    public const AFFILIATE_FAILED = 'FAILED';

    public const REVERSAL_PAYPAL_PROCESSOR = 'PAYPAL_PROCESSOR_REVERSAL';
    public const REVERSAL_PAYPAL_CHARGEBACK = 'PAYPAL_CHARGEBACK';
    public const REVERSAL_STRIPE_PROCESSOR = 'STRIPE_PROCESSOR_REVERSAL';
    public const REVERSAL_DUPLICATE_PAYMENT = 'DUPLICATE_PAYMENT_CORRECTION';
    public const REVERSAL_UNAUTHORIZED_PAYMENT = 'UNAUTHORIZED_PAYMENT';
    public const REVERSAL_BILLING_ERROR = 'TRADEX_BILLING_ERROR';
    public const REVERSAL_LEGAL_REQUIRED = 'LEGAL_REQUIRED_REVERSAL';

    protected $fillable = [
        'uuid',
        'payment_order_id',
        'user_id',
        'provider',
        'currency',
        'gross_amount_minor',
        'processor_fee_minor',
        'net_eligible_revenue_minor',
        'provider_capture_reference_hash',
        'provider_capture_reference',
        'status',
        'affiliate_processing_status',
        'affiliate_processed_at',
        'cleared_at',
        'payment_reversed_at',
        'payment_reversal_code',
        'payment_reversal_reason',
    ];

    protected $hidden = [
        'provider_capture_reference_hash',
        'provider_capture_reference',
        'payment_reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount_minor' => 'integer',
            'processor_fee_minor' => 'integer',
            'net_eligible_revenue_minor' => 'integer',
            'provider_capture_reference' => 'encrypted',
            'affiliate_processed_at' => 'datetime',
            'cleared_at' => 'datetime',
            'payment_reversed_at' => 'datetime',
            'payment_reversal_reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            if (blank($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            PaymentOrder::class,
            'payment_order_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PaymentAdjustment::class);
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
