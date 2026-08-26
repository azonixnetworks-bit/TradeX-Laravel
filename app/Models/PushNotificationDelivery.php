<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PushNotificationDelivery extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_QUEUED = 'QUEUED';
    public const STATUS_SENDING = 'SENDING';
    public const STATUS_RETRYING = 'RETRYING';
    public const STATUS_SENT = 'SENT';
    public const STATUS_INVALID_TOKEN = 'INVALID_TOKEN';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    public const TERMINAL_STATUSES = [
        self::STATUS_SENT,
        self::STATUS_INVALID_TOKEN,
        self::STATUS_FAILED,
        self::STATUS_SKIPPED,
    ];

    protected $fillable = [
        'uuid',
        'user_notification_receipt_id',
        'push_device_token_id',
        'user_id',
        'provider',
        'status',
        'attempts',
        'last_http_status',
        'provider_message_id',
        'last_error_code',
        'queued_at',
        'sent_at',
        'failed_at',
        'next_attempt_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_http_status' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $delivery): void {
            if (blank($delivery->uuid)) {
                $delivery->uuid = (string) Str::uuid();
            }
        });
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(
            UserNotificationReceipt::class,
            'user_notification_receipt_id'
        );
    }

    public function pushDeviceToken(): BelongsTo
    {
        return $this->belongsTo(PushDeviceToken::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }
}
