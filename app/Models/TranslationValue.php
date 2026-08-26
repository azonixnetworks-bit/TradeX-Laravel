<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TranslationValue extends Model
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_REVIEWED = 'REVIEWED';

    protected $fillable = [
        'uuid',
        'translation_key_id',
        'language_code',
        'translated_text',
        'source_sha256_at_translation',
        'translation_sha256',
        'status',
        'version',
        'updated_by_admin_id',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (self $translation): void {
                if (blank($translation->uuid)) {
                    $translation->uuid =
                        Str::uuid()->toString();
                }
            }
        );
    }

    public function translationKey(): BelongsTo
    {
        return $this->belongsTo(
            TranslationKey::class
        );
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
