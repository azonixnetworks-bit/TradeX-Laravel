<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Assignment extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Submission Method Constants
    |--------------------------------------------------------------------------
    */

    public const SUBMISSION_WHATSAPP = 'WHATSAPP';

    public const SUBMISSION_NONE = 'NONE';


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
        'heading',
        'description',
        'reference_image_path',
        'submission_method',
        'submission_instructions',
        'is_required',
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
            'is_required' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Model Boot
    |--------------------------------------------------------------------------
    |
    | UUID is generated server-side so admin forms and future API clients do
    | not need to provide or control the public assignment identifier.
    |
    */

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment): void {

            if (
                $assignment->uuid === null
                || trim((string) $assignment->uuid) === ''
            ) {
                $assignment->uuid = Str::uuid()->toString();
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


    public function scopeRequired(
        Builder $query
    ): Builder {
        return $query->where(
            'is_required',
            true
        );
    }


    public function scopeOptional(
        Builder $query
    ): Builder {
        return $query->where(
            'is_required',
            false
        );
    }


    public function scopeWhatsAppSubmission(
        Builder $query
    ): Builder {
        return $query->where(
            'submission_method',
            self::SUBMISSION_WHATSAPP
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
    | Assignment Policy Helpers
    |--------------------------------------------------------------------------
    */

    public function isRequired(): bool
    {
        return $this->is_required === true;
    }


    public function usesWhatsAppSubmission(): bool
    {
        return $this->submission_method === self::SUBMISSION_WHATSAPP;
    }


    public function hasExternalSubmission(): bool
    {
        return $this->submission_method !== self::SUBMISSION_NONE;
    }


    public function hasReferenceImage(): bool
    {
        return $this->reference_image_path !== null
            && trim($this->reference_image_path) !== '';
    }
}
