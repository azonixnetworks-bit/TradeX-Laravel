<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LanguageProfile extends Model
{
    protected $fillable = [
        'code',
        'english_name',
        'native_name',
        'direction',
        'is_source',
        'is_translation_enabled',
        'version',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_source' => 'boolean',
            'is_translation_enabled' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'updated_by_admin_id'
        );
    }

    public function translations(): HasMany
    {
        return $this->hasMany(
            TranslationValue::class,
            'language_code',
            'code'
        );
    }
}
