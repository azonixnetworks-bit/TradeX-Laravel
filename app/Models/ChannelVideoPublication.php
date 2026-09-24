<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChannelVideoPublication extends Model
{
    public const PLATFORM_YOUTUBE = 'YOUTUBE';
    public const PLATFORM_INSTAGRAM = 'INSTAGRAM';
    public const PLATFORM_FACEBOOK = 'FACEBOOK';
    public const PLATFORM_TELEGRAM = 'TELEGRAM';
    public const PLATFORM_TIKTOK = 'TIKTOK';
    public const PLATFORM_WHATSAPP_CHANNEL = 'WHATSAPP_CHANNEL';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_ACTION_REQUIRED = 'ACTION_REQUIRED';

    protected $fillable = [
        'uuid',
        'channel_video_id',
        'platform',
        'status',
        'attempt_count',
        'provider_post_id',
        'provider_url',
        'status_message',
        'response_meta',
        'queued_at',
        'started_at',
        'published_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'response_meta' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChannelVideoPublication $publication): void {
            if (
                $publication->uuid === null
                || trim((string) $publication->uuid) === ''
            ) {
                $publication->uuid = Str::uuid()->toString();
            }
        });
    }

    public function channelVideo(): BelongsTo
    {
        return $this->belongsTo(ChannelVideo::class);
    }

    public static function platformLabels(): array
    {
        return [
            self::PLATFORM_YOUTUBE => 'YouTube',
            self::PLATFORM_INSTAGRAM => 'Instagram Reels',
            self::PLATFORM_FACEBOOK => 'Facebook Reels',
            self::PLATFORM_TELEGRAM => 'Telegram Channel',
            self::PLATFORM_TIKTOK => 'TikTok',
            self::PLATFORM_WHATSAPP_CHANNEL => 'WhatsApp Channel',
        ];
    }

    public static function allPlatforms(): array
    {
        return array_keys(self::platformLabels());
    }

    public static function automaticPlatforms(): array
    {
        return [
            self::PLATFORM_YOUTUBE,
            self::PLATFORM_INSTAGRAM,
            self::PLATFORM_FACEBOOK,
            self::PLATFORM_TELEGRAM,
        ];
    }

    public static function actionRequiredPlatforms(): array
    {
        return [
            self::PLATFORM_TIKTOK,
            self::PLATFORM_WHATSAPP_CHANNEL,
        ];
    }

    public function platformLabel(): string
    {
        return self::platformLabels()[$this->platform]
            ?? $this->platform;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isRetryable(): bool
    {
        return in_array(
            $this->platform,
            self::automaticPlatforms(),
            true
        ) && in_array(
            $this->status,
            [
                self::STATUS_FAILED,
                self::STATUS_ACTION_REQUIRED,
            ],
            true
        );
    }
}
