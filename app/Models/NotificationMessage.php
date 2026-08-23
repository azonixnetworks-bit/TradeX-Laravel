<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NotificationMessage extends Model
{
    public const AUDIENCE_ALL = 'ALL';
    public const AUDIENCE_FREE = 'FREE';
    public const AUDIENCE_PREMIUM = 'PREMIUM';

    public const CATEGORY_GENERAL = 'GENERAL';
    public const CATEGORY_SYSTEM = 'SYSTEM';
    public const CATEGORY_EDUCATION = 'EDUCATION';
    public const CATEGORY_TRADE_IDEA = 'TRADE_IDEA';
    public const CATEGORY_AFFILIATE = 'AFFILIATE';
    public const CATEGORY_PAYMENT = 'PAYMENT';
    public const CATEGORY_SECURITY = 'SECURITY';

    public const CATEGORIES = [
        self::CATEGORY_GENERAL,
        self::CATEGORY_SYSTEM,
        self::CATEGORY_EDUCATION,
        self::CATEGORY_TRADE_IDEA,
        self::CATEGORY_AFFILIATE,
        self::CATEGORY_PAYMENT,
        self::CATEGORY_SECURITY,
    ];

    public const PRIORITY_NORMAL = 'NORMAL';
    public const PRIORITY_HIGH = 'HIGH';
    public const PRIORITY_CRITICAL = 'CRITICAL';

    public const PRIORITIES = [
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const ACTION_NONE = 'NONE';
    public const ACTION_DASHBOARD = 'DASHBOARD';
    public const ACTION_EDUCATION_COURSE = 'EDUCATION_COURSE';
    public const ACTION_EDUCATION_LESSON = 'EDUCATION_LESSON';
    public const ACTION_TRADE_IDEA = 'TRADE_IDEA';
    public const ACTION_AFFILIATE = 'AFFILIATE';
    public const ACTION_OUR_CHANNEL_VIDEO = 'OUR_CHANNEL_VIDEO';
    public const ACTION_PROFILE = 'PROFILE';

    public const ACTION_TYPES = [
        self::ACTION_NONE,
        self::ACTION_DASHBOARD,
        self::ACTION_EDUCATION_COURSE,
        self::ACTION_EDUCATION_LESSON,
        self::ACTION_TRADE_IDEA,
        self::ACTION_AFFILIATE,
        self::ACTION_OUR_CHANNEL_VIDEO,
        self::ACTION_PROFILE,
    ];

    protected $fillable = [
        'uuid',
        'audience_access_level',
        'category',
        'priority',
        'title',
        'body',
        'action_type',
        'action_resource_uuid',
        'is_active',
        'published_at',
        'expires_at',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (blank($message->uuid)) {
                $message->uuid =
                    (string) Str::uuid();
            }
        });
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(
            UserNotificationReceipt::class,
            'notification_message_id'
        );
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function scopeCurrentlyPublished(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'is_active',
                true
            )
            ->whereNotNull(
                'published_at'
            )
            ->where(
                'published_at',
                '<=',
                now()
            )
            ->where(
                function (
                    Builder $expiry
                ): void {
                    $expiry
                        ->whereNull(
                            'expires_at'
                        )
                        ->orWhere(
                            'expires_at',
                            '>',
                            now()
                        );
                }
            );
    }
}
