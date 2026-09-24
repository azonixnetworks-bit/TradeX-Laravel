<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SecondBrainKnowledgeItem extends Model
{
    public const MARKETS = [
        'COMMON',
        'FOREX',
        'CRYPTO',
        'DERIV',
        'STOCKS',
        'MULTI',
    ];

    public const TIMEFRAMES = [
        'MONTHLY',
        'WEEKLY',
        'DAILY',
        'H4',
        'H2',
        'H1',
        'M30',
        'M15',
        'M10',
        'M5',
        'M3',
        'M2',
        'M1',
    ];

    public const KNOWLEDGE_TYPES = [
        'NOTE',
        'RULE',
        'SETUP',
        'EXAMPLE',
        'COUNTER_EXAMPLE',
        'EXCEPTION',
        'MISTAKE',
        'PSYCHOLOGY',
        'REFERENCE',
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_AI_EXTRACTED = 'AI_EXTRACTED';
    public const STATUS_NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_VECTOR_INDEXED = 'VECTOR_INDEXED';
    public const STATUS_MCP_SYNCED = 'MCP_SYNCED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_DEPRECATED = 'DEPRECATED';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_AI_EXTRACTED,
        self::STATUS_NEEDS_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_VECTOR_INDEXED,
        self::STATUS_MCP_SYNCED,
        self::STATUS_ACTIVE,
        self::STATUS_DEPRECATED,
    ];

    protected $table = 'second_brain_knowledge_items';

    protected $fillable = [
        'uuid',
        'subject_id',
        'heading',
        'title',
        'notes',
        'market',
        'timeframes',
        'knowledge_type',
        'tags',
        'status',
        'version',
        'founder_approved_at',
        'approved_by_admin_id',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'timeframes' => 'array',
            'tags' => 'array',
            'version' => 'integer',
            'founder_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (blank($item->uuid)) {
                $item->uuid = (string) Str::uuid();
            }

            $item->market = strtoupper(trim((string) $item->market));
            $item->knowledge_type = strtoupper(trim((string) $item->knowledge_type));
            $item->status = strtoupper(trim((string) $item->status));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(SecondBrainKnowledgeSubject::class, 'subject_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(SecondBrainKnowledgeMedia::class, 'knowledge_item_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'approved_by_admin_id');
    }
}
