<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationAutomationEvent extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_PROCESSED = 'PROCESSED';

    protected $fillable = [
        'event_key',
        'event_type',
        'source_type',
        'source_reference',
        'payload',
        'status',
        'attempts',
        'next_attempt_at',
        'last_error_code',
        'notification_message_id',
        'recipient_count',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'recipient_count' => 'integer',
            'next_attempt_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function notificationMessage(): BelongsTo
    {
        return $this->belongsTo(
            NotificationMessage::class,
            'notification_message_id'
        );
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }
}
