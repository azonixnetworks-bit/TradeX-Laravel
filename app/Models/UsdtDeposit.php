<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UsdtDeposit extends Model
{
    public const NETWORK_TRC20 = 'TRC20';

    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'payment_order_id',
        'user_id',
        'network',
        'tx_hash',
        'sender_address',
        'amount_micro',
        'status',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_reason',
    ];

    protected $hidden = [
        'sender_address',
        'reviewed_by_admin_id',
        'review_reason',
    ];

    protected function casts(): array
    {
        return [
            'sender_address' => 'encrypted',
            'amount_micro' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $deposit): void {
            if (blank($deposit->uuid)) {
                $deposit->uuid = (string) Str::uuid();
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

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'reviewed_by_admin_id'
        );
    }
}
