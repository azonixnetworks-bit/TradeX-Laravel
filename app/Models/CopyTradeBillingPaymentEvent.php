<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingPaymentEvent extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_LINKED = 'LINKED';
    public const EVENT_CLEARED = 'CLEARED';
    public const EVENT_REVERSED = 'REVERSED';

    protected $fillable = [
        'uuid',
        'billing_invoice_id',
        'payment_order_id',
        'payment_id',
        'event_type',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade billing payment events are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing payment events cannot be deleted through Eloquent.'
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

    public function paymentOrder(): BelongsTo
    {
        return $this->belongsTo(PaymentOrder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
