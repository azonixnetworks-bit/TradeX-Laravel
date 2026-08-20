<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Course extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Education Constants
    |--------------------------------------------------------------------------
    */

    public const ACCESS_FREE = 'FREE';

    public const ACCESS_PREMIUM = 'PREMIUM';

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
        'code',
        'slug',
        'title',
        'short_description',
        'description',
        'thumbnail_path',
        'language_code',
        'access_level',
        'status',
        'published_at',
        'sort_order',
        'is_featured',
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
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Model Boot
    |--------------------------------------------------------------------------
    |
    | UUID is generated server-side so controllers, jobs and future API
    | endpoints do not have to trust a client-provided UUID.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (Course $course): void {

            if (
                $course->uuid === null
                || trim((string) $course->uuid) === ''
            ) {
                $course->uuid = Str::uuid()->toString();
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function modules(): HasMany
    {
        return $this->hasMany(
            CourseModule::class,
            'course_id'
        )->orderBy('sort_order')
            ->orderBy('id');
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


    public function scopeFeatured(
        Builder $query
    ): Builder {
        return $query->where(
            'is_featured',
            true
        );
    }


    public function scopeFree(
        Builder $query
    ): Builder {
        return $query->where(
            'access_level',
            self::ACCESS_FREE
        );
    }


    public function scopePremium(
        Builder $query
    ): Builder {
        return $query->where(
            'access_level',
            self::ACCESS_PREMIUM
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
    | State Helpers
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


    public function isFree(): bool
    {
        return $this->access_level === self::ACCESS_FREE;
    }


    public function isPremium(): bool
    {
        return $this->access_level === self::ACCESS_PREMIUM;
    }
}
