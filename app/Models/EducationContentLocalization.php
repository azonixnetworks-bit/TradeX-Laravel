<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EducationContentLocalization extends Model
{
    public const TYPE_COURSE = 'COURSE';
    public const TYPE_MODULE = 'MODULE';
    public const TYPE_LESSON = 'LESSON';
    public const TYPE_VIDEO = 'VIDEO';
    public const TYPE_ASSIGNMENT = 'ASSIGNMENT';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEWED = 'REVIEWED';

    protected $fillable = [
        'uuid',
        'content_type',
        'content_id',
        'language_code',
        'title',
        'heading',
        'short_description',
        'description',
        'submission_instructions',
        'source_sha256',
        'translation_sha256',
        'status',
        'version',
        'updated_by_admin_id',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $hidden = [
        'source_sha256',
        'translation_sha256',
    ];

    protected function casts(): array
    {
        return [
            'content_id' => 'integer',
            'version' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $localization): void {
            if (blank($localization->uuid)) {
                $localization->uuid = Str::uuid()->toString();
            }
        });
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(
            LanguageProfile::class,
            'language_code',
            'code'
        );
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function reviewedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'reviewed_by_admin_id'
        );
    }
}
