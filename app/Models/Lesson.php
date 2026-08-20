<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Education Constants
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
        'course_module_id',
        'code',
        'slug',
        'title',
        'heading',
        'short_description',
        'description',
        'is_preview',
        'status',
        'published_at',
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
            'is_preview' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Model Boot
    |--------------------------------------------------------------------------
    |
    | UUID generation stays server-side so admin forms and future APIs do not
    | need to provide or be trusted with primary public identifiers.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (Lesson $lesson): void {

            if (
                $lesson->uuid === null
                || trim((string) $lesson->uuid) === ''
            ) {
                $lesson->uuid = Str::uuid()->toString();
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function module(): BelongsTo
    {
        return $this->belongsTo(
            CourseModule::class,
            'course_module_id'
        );
    }


    public function videos(): HasMany
    {
        return $this->hasMany(
            LessonVideo::class,
            'lesson_id'
        )->orderBy('sort_order')
            ->orderBy('id');
    }


    public function assignments(): HasMany
    {
        return $this->hasMany(
            Assignment::class,
            'lesson_id'
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

    public function scopeForModule(
        Builder $query,
        CourseModule|int $module
    ): Builder {
        $moduleId = $module instanceof CourseModule
            ? $module->getKey()
            : $module;

        return $query->where(
            'course_module_id',
            $moduleId
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


    public function scopePreview(
        Builder $query
    ): Builder {
        return $query->where(
            'is_preview',
            true
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


    public function isPreview(): bool
    {
        return $this->is_preview === true;
    }
}
