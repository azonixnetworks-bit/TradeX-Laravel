<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DailyTradeIdea extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_ARCHIVED = 'ARCHIVED';

    protected $fillable = [
        'uuid',
        'title',
        'summary',
        'analysis',
        'tradingview_url',
        'chart_image_disk',
        'chart_image_path',
        'chart_image_mime_type',
        'chart_image_size_bytes',
        'category',
        'market_region',
        'symbol',
        'instrument_name',
        'direction',
        'timeframe',
        'entry_price_low',
        'entry_price_high',
        'stop_loss',
        'status',
        'published_at',
        'archived_at',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_price_low' => 'decimal:10',
            'entry_price_high' => 'decimal:10',
            'stop_loss' => 'decimal:10',
            'chart_image_size_bytes' => 'integer',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DailyTradeIdea $idea): void {
            if ($idea->uuid === null || trim((string) $idea->uuid) === '') {
                $idea->uuid = Str::uuid()->toString();
            }
        });
    }

    public function targets(): HasMany
    {
        return $this->hasMany(DailyTradeIdeaTarget::class)
            ->orderBy('sort_order');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function hasChartImage(): bool
    {
        return $this->chart_image_disk !== null
            && $this->chart_image_path !== null;
    }

    public function displayEntryZone(): string
    {
        if ($this->entry_price_low === null && $this->entry_price_high === null) {
            return '—';
        }

        if ($this->entry_price_low === null) {
            return '≤ '.$this->entry_price_high;
        }

        if ($this->entry_price_high === null) {
            return '≥ '.$this->entry_price_low;
        }

        if ($this->entry_price_low === $this->entry_price_high) {
            return (string) $this->entry_price_low;
        }

        return $this->entry_price_low.' – '.$this->entry_price_high;
    }
}
