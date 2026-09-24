<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SecondBrainKnowledgeSubject extends Model
{
    public const MARKET_SCOPES = [
        'COMMON',
        'FOREX',
        'CRYPTO',
        'DERIV',
        'STOCKS',
        'MULTI',
    ];

    protected $table = 'second_brain_knowledge_subjects';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'market_scope',
        'is_active',
        'sort_order',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subject): void {
            if (blank($subject->uuid)) {
                $subject->uuid = (string) Str::uuid();
            }

            $subject->name = trim((string) $subject->name);
            $subject->market_scope = strtoupper(trim((string) $subject->market_scope));

            if (blank($subject->slug)) {
                $subject->slug = Str::slug($subject->name).'-'.strtolower($subject->market_scope);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SecondBrainKnowledgeItem::class, 'subject_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
