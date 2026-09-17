<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorReminder extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    public const TYPE_DUE_7_DAYS = 'DUE_7_DAYS';
    public const TYPE_DUE_3_DAYS = 'DUE_3_DAYS';
    public const TYPE_DUE_TODAY = 'DUE_TODAY';
    public const TYPE_OVERDUE = 'OVERDUE';
    public const TYPE_PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';

    protected $fillable = [
        'uuid',
        'trading_floor_client_id',
        'trading_floor_commission_invoice_id',
        'trading_floor_commission_payment_id',
        'reminder_type',
        'channel',
        'recipient',
        'dedupe_key',
        'status',
        'scheduled_for',
        'sent_at',
        'provider_reference',
        'payload',
        'last_error',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $reminder): void {
            $reminder->uuid ??= (string) Str::uuid();
            $reminder->reminder_type = strtoupper(trim((string) $reminder->reminder_type));
            $reminder->channel = strtoupper(trim((string) ($reminder->channel ?: 'IN_APP')));
            $reminder->status = strtoupper(trim((string) ($reminder->status ?: self::STATUS_PENDING)));
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TradingFloorClient::class, 'trading_floor_client_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TradingFloorCommissionInvoice::class, 'trading_floor_commission_invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TradingFloorCommissionPayment::class, 'trading_floor_commission_payment_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
