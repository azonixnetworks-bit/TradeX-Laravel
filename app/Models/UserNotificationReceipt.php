<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserNotificationReceipt extends Model
{
    protected $fillable = [
        'uuid',
        'notification_message_id',
        'user_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $receipt): void {
            if (blank($receipt->uuid)) {
                $receipt->uuid =
                    (string) Str::uuid();
            }
        });
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(
            NotificationMessage::class,
            'notification_message_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
