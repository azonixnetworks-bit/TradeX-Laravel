<?php

namespace App\Models;

use App\Support\YouTubeVideo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ChannelVideo extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'uuid',
        'title',
        'short_description',
        'description',
        'youtube_video_id',
        'youtube_url',
        'language_code',
        'status',
        'published_at',
        'is_featured',
        'sort_order',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ChannelVideo $video): void {
            if (
                $video->uuid === null
                || trim((string) $video->uuid) === ''
            ) {
                $video->uuid = Str::uuid()->toString();
            }
        });
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function scopePublished(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'status',
                self::STATUS_PUBLISHED
            )
            ->whereNotNull(
                'published_at'
            )
            ->where(
                'published_at',
                '<=',
                now()
            );
    }

    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'sort_order'
            )
            ->orderByDesc(
                'published_at'
            )
            ->orderBy(
                'id'
            );
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function canonicalYouTubeUrl(): string
    {
        return YouTubeVideo::canonicalUrl(
            $this->youtube_video_id
        );
    }

    public function embedUrl(): string
    {
        return YouTubeVideo::embedUrl(
            $this->youtube_video_id
        );
    }

    public function thumbnailUrl(): string
    {
        return YouTubeVideo::thumbnailUrl(
            $this->youtube_video_id
        );
    }
}
