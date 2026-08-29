<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class CopyTradeBillingInvoiceLine extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'billing_invoice_id',
        'line_type',
        'description',
        'amount_minor',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            if (blank($line->uuid)) {
                $line->uuid = (string) Str::uuid();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Copy Trade billing invoice lines are immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Copy Trade billing invoice lines cannot be deleted through Eloquent.'
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
}
