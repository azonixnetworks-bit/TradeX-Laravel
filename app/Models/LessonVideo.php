<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LessonVideo extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Processing Status Constants
    |--------------------------------------------------------------------------
    */

    public const PROCESSING_PENDING = 'PENDING';

    public const PROCESSING_PROCESSING = 'PROCESSING';

    public const PROCESSING_READY = 'READY';

    public const PROCESSING_FAILED = 'FAILED';

    /*
    |--------------------------------------------------------------------------
    | Publication Status Constants
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const STATUS_ARCHIVED = 'ARCHIVED';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'uuid',
        'lesson_id',
        'title',
        'short_description',
        'description',
        'storage_disk',
        'source_path',
        'hls_master_path',
        'streaming_asset_key',
        'thumbnail_path',
        'duration_seconds',
        'file_size_bytes',
        'mime_type',
        'processing_status',
        'processing_progress',
        'processing_stage',
        'processing_error',
        'processed_at',
        'status',
        'published_at',
        'is_downloadable',
        'sort_order',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | Attribute Casting
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'file_size_bytes' => 'integer',
            'processing_progress' => 'integer',
            'processed_at' => 'datetime',
            'published_at' => 'datetime',
            'is_downloadable' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Model Boot
    |--------------------------------------------------------------------------
    |
    | Video UUIDs are generated server-side. Cloud paths, provider asset IDs
    | and all playback authorization remain controlled by the application.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (LessonVideo $video): void {

            if (
                $video->uuid === null
                || trim((string) $video->uuid) === ''
            ) {
                $video->uuid = Str::uuid()->toString();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(
            Lesson::class,
            'lesson_id'
        );
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

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForLesson(
        Builder $query,
        Lesson|int $lesson
    ): Builder {
        $lessonId = $lesson instanceof Lesson
            ? $lesson->getKey()
            : $lesson;

        return $query->where(
            'lesson_id',
            $lessonId
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

    public function scopeReady(
        Builder $query
    ): Builder {
        return $query->where(
            'processing_status',
            self::PROCESSING_READY
        );
    }

    public function scopePlaybackReady(
        Builder $query
    ): Builder {
        return $query
            ->ready()
            ->published();
    }

    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeArchived(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ARCHIVED
        );
    }

    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'id'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Processing State Helpers
    |--------------------------------------------------------------------------
    */

    public function isProcessingPending(): bool
    {
        return $this->processing_status === self::PROCESSING_PENDING;
    }

    public function isProcessing(): bool
    {
        return $this->processing_status === self::PROCESSING_PROCESSING;
    }

    public function isReady(): bool
    {
        return $this->processing_status === self::PROCESSING_READY;
    }

    public function hasProcessingFailed(): bool
    {
        return $this->processing_status === self::PROCESSING_FAILED;
    }

    /*
    |--------------------------------------------------------------------------
    | Publication State Helpers
    |--------------------------------------------------------------------------
    */

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

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /*
    |--------------------------------------------------------------------------
    | Playback Reference Helpers
    |--------------------------------------------------------------------------
    |
    | These methods only indicate whether a stable cloud/media reference is
    | stored. They intentionally do not generate public or signed URLs.
    |
    */

    public function hasHlsStream(): bool
    {
        return $this->hls_master_path !== null
            && trim($this->hls_master_path) !== '';
    }

    public function hasStreamingAsset(): bool
    {
        return $this->streaming_asset_key !== null
            && trim($this->streaming_asset_key) !== '';
    }

    public function hasPlaybackReference(): bool
    {
        return $this->hasHlsStream()
            || $this->hasStreamingAsset();
    }

    public function isPlaybackReady(): bool
    {
        return $this->isReady()
            && $this->isPublished()
            && $this->hasPlaybackReference();
    }

    public function isDownloadable(): bool
    {
        return $this->is_downloadable === true;
    }
}
