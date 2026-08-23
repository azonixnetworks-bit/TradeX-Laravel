<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EducationPlaybackSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'token_hash',
        'user_id',
        'user_device_id',
        'lesson_video_id',
        'sanctum_session_uuid',
        'request_ip',
        'user_agent_hash',
        'expires_at',
        'last_used_at',
        'use_count',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
        'request_ip',
        'user_agent_hash',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(
            UserDevice::class,
            'user_device_id'
        );
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(
            LessonVideo::class,
            'lesson_video_id'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(): bool
    {
        return ! $this->isExpired()
            && ! $this->isRevoked();
    }
}
