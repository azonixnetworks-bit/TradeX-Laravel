<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingInvoice extends Model
{
    public const TYPE_INITIAL = 'INITIAL';
    public const TYPE_RENEWAL = 'RENEWAL';
    public const TYPE_UPGRADE = 'UPGRADE';

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_PAID = 'PAID';
    public const STATUS_VOID = 'VOID';
    public const STATUS_PAYMENT_REVERSED = 'PAYMENT_REVERSED';

    protected $fillable = [
        'uuid',
        'invoice_number',
        'idempotency_key_hash',
        'user_id',
        'broker_account_id',
        'billing_cycle_id',
        'billing_snapshot_id',
        'payment_order_id',
        'payment_id',
        'invoice_type',
        'product_code',
        'currency',
        'subtotal_minor',
        'total_minor',
        'status',
        'period_start',
        'period_end',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            if (blank($invoice->uuid)) {
                $invoice->uuid = (string) Str::uuid();
            }

            if (blank($invoice->invoice_number)) {
                $invoice->invoice_number =
                    'CTX-'.now()->format('Ym').'-'
                    .strtoupper(substr(str_replace('-', '', $invoice->uuid), 0, 12));
            }
        });

        static::updating(function (self $invoice): void {
            if (
                $invoice->getOriginal('issued_at') !== null
                && $invoice->isDirty([
                    'user_id',
                    'broker_account_id',
                    'billing_cycle_id',
                    'billing_snapshot_id',
                    'invoice_type',
                    'product_code',
                    'currency',
                    'subtotal_minor',
                    'total_minor',
                    'period_start',
                    'period_end',
                ])
            ) {
                throw new LogicException(
                    'Issued Copy Trade invoice calculation fields are immutable.'
                );
            }
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing invoices cannot be deleted through Eloquent.'
            );
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brokerAccount(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerAccount::class,
            'broker_account_id'
        );
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBillingCycle::class,
            'billing_cycle_id'
        );
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBillingSnapshot::class,
            'billing_snapshot_id'
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

    public function lines(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingInvoiceLine::class,
            'billing_invoice_id'
        );
    }

    public function paymentEvents(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingPaymentEvent::class,
            'billing_invoice_id'
        )->latest('created_at');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
