<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PaymentWebhookEvent extends Model
{
    public const PROVIDER_PAYPAL = 'PAYPAL';

    public const STATUS_RECEIVED = 'RECEIVED';
    public const STATUS_PROCESSED = 'PROCESSED';
    public const STATUS_IGNORED = 'IGNORED';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'uuid',
        'provider',
        'event_id',
        'event_type',
        'payload_sha256',
        'status',
        'processed_at',
    ];

    protected $hidden = [
        'event_id',
        'payload_sha256',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }
        });
    }
}
