<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TradingFloorCommissionInvoice extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';
    public const STATUS_PAID = 'PAID';
    public const STATUS_OVERDUE = 'OVERDUE';
    public const STATUS_WAIVED = 'WAIVED';
    public const STATUS_DISPUTED = 'DISPUTED';

    protected $fillable = [
        'uuid', 'invoice_number', 'trading_floor_client_id', 'period_start', 'period_end',
        'due_date', 'commission_type', 'trading_profit_amount', 'commission_rate',
        'commission_amount', 'paid_amount', 'balance_amount', 'currency', 'status',
        'notes', 'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'due_date' => 'date',
            'trading_profit_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->uuid ??= (string) Str::uuid();
            $invoice->status = strtoupper((string) ($invoice->status ?: self::STATUS_PENDING));
            $invoice->commission_type = strtoupper((string) $invoice->commission_type);
            $invoice->currency = strtoupper((string) ($invoice->currency ?: 'LKR'));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TradingFloorClient::class, 'trading_floor_client_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TradingFloorCommissionPayment::class);
    }
}
