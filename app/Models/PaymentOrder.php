<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class PaymentOrder extends Model
{
    public const PRODUCT_EDUCATION_PREMIUM =
        'EDUCATION_PREMIUM';

    public const PROVIDER_PAYPAL =
        'PAYPAL';
    public const PROVIDER_STRIPE =
        'STRIPE';
    public const PROVIDER_NOWPAYMENTS =
        'NOWPAYMENTS';
    public const PROVIDER_LOCAL_BANK =
        'LOCAL_BANK';
    public const PROVIDER_USDT_TRC20 =
        'USDT_TRC20';
    public const PROVIDER_CRYPTO_MANUAL =
        'CRYPTO_MANUAL';

    public const STATUS_CREATED =
        'CREATED';
    public const STATUS_AWAITING_USER_ACTION =
        'AWAITING_USER_ACTION';
    public const STATUS_PENDING_REVIEW =
        'PENDING_REVIEW';
    public const STATUS_PROCESSING =
        'PROCESSING';
    public const STATUS_CLEARED =
        'CLEARED';
    public const STATUS_FAILED =
        'FAILED';
    public const STATUS_CANCELLED =
        'CANCELLED';
    public const STATUS_PAYMENT_REVERSED =
        'PAYMENT_REVERSED';

    protected $fillable = [
        'uuid',
        'user_id',
        'receiving_account_id',
        'product_code',
        'provider',
        'country_code',
        'currency',
        'amount_minor',
        'status',
        'client_idempotency_key_hash',
        'provider_reference',
        'manual_reference',
        'receiving_account_snapshot',
        'approval_url',
        'failure_code',
        'cleared_at',
        'expires_at',
    ];

    protected $hidden = [
        'provider_reference',
        'approval_url',
        'client_idempotency_key_hash',
        'receiving_account_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' =>
                'integer',
            'receiving_account_snapshot' =>
                'encrypted:array',
            'approval_url' =>
                'encrypted',
            'cleared_at' =>
                'datetime',
            'expires_at' =>
                'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (
                self $order
            ): void {
                if (
                    blank(
                        $order->uuid
                    )
                ) {
                    $order->uuid =
                        (string) Str::uuid();
                }
            }
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function receivingAccount(): BelongsTo
    {
        return $this->belongsTo(
            PaymentReceivingAccount::class,
            'receiving_account_id'
        );
    }

    public function payment(): HasOne
    {
        return $this->hasOne(
            Payment::class
        );
    }

    public function bankDeposit(): HasOne
    {
        return $this->hasOne(
            BankDeposit::class
        );
    }

    public function usdtDeposit(): HasOne
    {
        return $this->hasOne(
            UsdtDeposit::class
        );
    }

    public function cryptoDeposit(): HasOne
    {
        return $this->hasOne(
            CryptoDeposit::class
        );
    }

    public function isCleared(): bool
    {
        return $this->status
            === self::STATUS_CLEARED;
    }

    public function isPaymentReversed(): bool
    {
        return $this->status
            === self::STATUS_PAYMENT_REVERSED;
    }
}
