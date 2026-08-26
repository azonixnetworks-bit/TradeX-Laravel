<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

class TranslationKey extends Model
{
    protected $fillable = [
        'uuid',
        'group_name',
        'translation_key',
        'source_text',
        'source_sha256',
        'version',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (self $key): void {
                if (blank($key->uuid)) {
                    $key->uuid =
                        Str::uuid()->toString();
                }
            }
        );

        static::updating(
            function (self $key): void {
                if (
                    $key->isDirty('translation_key')
                    || $key->isDirty('group_name')
                ) {
                    throw new LogicException(
                        'Translation key identity is immutable.'
                    );
                }
            }
        );

        static::deleting(
            function (): void {
                throw new LogicException(
                    'Translation keys cannot be deleted.'
                );
            }
        );
    }

    public function translations(): HasMany
    {
        return $this->hasMany(
            TranslationValue::class
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
}
