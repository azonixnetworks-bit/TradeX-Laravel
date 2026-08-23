<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankDeposit extends Model
{
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'uuid',
        'payment_order_id',
        'user_id',
        'depositor_name',
        'bank_reference',
        'deposit_date',
        'proof_disk',
        'proof_path',
        'status',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_at',
        'review_reason',
    ];

    protected $hidden = [
        'depositor_name',
        'bank_reference',
        'proof_disk',
        'proof_path',
        'reviewed_by_admin_id',
        'review_reason',
    ];

    protected function casts(): array
    {
        return [
            'depositor_name' => 'encrypted',
            'bank_reference' => 'encrypted',
            'deposit_date' => 'date',
            'proof_path' => 'encrypted',
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
