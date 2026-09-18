<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorCommissionPayment extends Model
{
    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_REVERSED = 'REVERSED';

    protected $fillable = [
        'uuid',
        'trading_floor_commission_invoice_id',
        'amount',
        'currency',
        'payment_method',
        'transaction_reference',
        'proof_path',
        'received_at',
        'received_by_admin_id',
        'status',
        'notes',
        'reversed_at',
        'reversed_by_admin_id',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->uuid ??= (string) Str::uuid();
            $payment->currency = strtoupper(trim((string) $payment->currency));
            $payment->payment_method = strtoupper(trim((string) $payment->payment_method));
            $payment->status = strtoupper(trim((string) ($payment->status ?: self::STATUS_RECEIVED)));
            $payment->received_at ??= now();
        });

        static::updating(function (self $payment): void {
            $payment->status = strtoupper(trim((string) $payment->status));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TradingFloorCommissionInvoice::class, 'trading_floor_commission_invoice_id');
    }

    public function receivedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'received_by_admin_id');
    }

    public function reversedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reversed_by_admin_id');
    }
}
