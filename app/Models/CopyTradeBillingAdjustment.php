<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingAdjustment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'billing_invoice_id',
        'broker_account_id',
        'created_by_admin_id',
        'adjustment_type',
        'amount_minor',
        'reason',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'reason' => 'encrypted',
            'evidence' => 'encrypted:array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (blank($adjustment->uuid)) {
                $adjustment->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade billing adjustments are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing adjustments cannot be deleted through Eloquent.'
            );
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBillingInvoice::class,
            'billing_invoice_id'
        );
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
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
