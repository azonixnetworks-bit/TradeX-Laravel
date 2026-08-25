<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PayoutRequest extends Model
{
    public const METHOD_PAYPAL = 'PAYPAL';
    public const METHOD_BINANCE = 'BINANCE';
    public const METHOD_USDT_TRC20 = 'USDT_TRC20';
    public const METHOD_LOCAL_BANK = 'LOCAL_BANK';
    public const METHODS = [self::METHOD_PAYPAL, self::METHOD_BINANCE, self::METHOD_USDT_TRC20, self::METHOD_LOCAL_BANK];

    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUSES = [self::STATUS_PENDING_REVIEW, self::STATUS_APPROVED, self::STATUS_PROCESSING, self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_FAILED, self::STATUS_CANCELLED];
    public const BALANCE_CONSUMING_STATUSES = [self::STATUS_PENDING_REVIEW, self::STATUS_APPROVED, self::STATUS_PROCESSING, self::STATUS_PAID];
    public const ACTIVE_STATUSES = [self::STATUS_PENDING_REVIEW, self::STATUS_APPROVED, self::STATUS_PROCESSING];

    protected $fillable = ['uuid','user_id','method','status','amount_cents','currency','destination_snapshot','destination_fingerprint','idempotency_key_hash','provider_reference','user_visible_reason','internal_notes','requested_at','approved_at','processing_at','paid_at','rejected_at','failed_at','cancelled_at','reviewed_by_admin_id'];
    protected $hidden = ['destination_snapshot','destination_fingerprint','idempotency_key_hash','internal_notes','reviewed_by_admin_id'];

    protected function casts(): array
    {
        return [
            'amount_cents'=>'integer',
            'destination_snapshot'=>'encrypted:array',
            'provider_reference'=>'encrypted',
            'user_visible_reason'=>'encrypted',
            'internal_notes'=>'encrypted',
            'requested_at'=>'datetime','approved_at'=>'datetime','processing_at'=>'datetime','paid_at'=>'datetime','rejected_at'=>'datetime','failed_at'=>'datetime','cancelled_at'=>'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payout): void {
            if (blank($payout->uuid)) $payout->uuid = (string) Str::uuid();
        });
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function allocations(): HasMany { return $this->hasMany(PayoutAllocation::class); }
    public function reviewedByAdmin(): BelongsTo { return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_id'); }
    public function consumesBalance(): bool { return in_array($this->status, self::BALANCE_CONSUMING_STATUSES, true); }
    public function isActive(): bool { return in_array($this->status, self::ACTIVE_STATUSES, true); }
    public function isPaid(): bool { return $this->status === self::STATUS_PAID; }
}
