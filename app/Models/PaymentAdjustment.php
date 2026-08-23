<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAdjustment extends Model
{
    public const TYPE_PAYMENT_REVERSAL = 'PAYMENT_REVERSAL';

    protected $fillable = [
        'uuid',
        'payment_id',
        'type',
        'amount_minor',
        'currency',
        'reason',
        'provider_event_id',
    ];

    protected $hidden = [
        'reason',
        'provider_event_id',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $adjustment): void {
            if (blank($adjustment->uuid)) {
                $adjustment->uuid = (string) Str::uuid();
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
