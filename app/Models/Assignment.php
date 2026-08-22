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

    public const SUBMISSION_WHATSAPP = 'WHATSAPP';
    public const SUBMISSION_NONE = 'NONE';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    /*
    |--------------------------------------------------------------------------
    | Admin Assignment Material Policy
    |--------------------------------------------------------------------------
    |
    | These files are uploaded BY an authorized admin as assignment/question/
    | reference material. They are NOT learner/student answer submissions.
    |
    */
    public const MATERIAL_DISK = 'private';
    public const MATERIAL_MAX_UPLOAD_KB = 25600;

    public const MATERIAL_ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'docx',
        'xlsx',
        'pptx',
        'txt',
    ];

    protected $fillable = [
        'uuid',
        'lesson_id',
        'title',
        'heading',
        'description',
        'reference_image_path',
        'material_disk',
        'material_path',
        'material_original_name',
        'material_mime_type',
        'material_size_bytes',
        'material_sha256',
        'material_uploaded_at',
        'submission_method',
        'submission_instructions',
        'is_required',
        'status',
        'published_at',
        'sort_order',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'material_size_bytes' => 'integer',
            'material_uploaded_at' => 'datetime',
            'is_required' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

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

    public function hasMaterial(): bool
    {
        return $this->material_disk !== null
            && trim((string) $this->material_disk) !== ''
            && $this->material_path !== null
            && trim((string) $this->material_path) !== '';
    }

    public function materialDisplayName(): ?string
    {
        if (! $this->hasMaterial()) {
            return null;
        }

        $name = trim(
            (string) $this->material_original_name
        );

        return $name !== ''
            ? $name
            : 'Assignment material';
    }
}
