<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CourseModule extends Model
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
        'course_id',
        'code',
        'slug',
        'title',
        'short_description',
        'description',
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
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Model Boot
    |--------------------------------------------------------------------------
    |
    | Generate the module UUID on the server when it is not supplied.
    | Business identifiers such as code and slug remain explicit so their
    | uniqueness and naming can be controlled by the Education application
    | layer.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (CourseModule $module): void {

            if (
                $module->uuid === null
                || trim((string) $module->uuid) === ''
            ) {
                $module->uuid = Str::uuid()->toString();
            }
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function course(): BelongsTo
    {
        return $this->belongsTo(
            Course::class,
            'course_id'
        );
    }


    public function lessons(): HasMany
    {
        return $this->hasMany(
            Lesson::class,
            'course_module_id'
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

    public function scopeForCourse(
        Builder $query,
        Course|int $course
    ): Builder {
        $courseId = $course instanceof Course
            ? $course->getKey()
            : $course;

        return $query->where(
            'course_id',
            $courseId
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
}
