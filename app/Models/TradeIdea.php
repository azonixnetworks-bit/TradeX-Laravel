<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TradeIdea extends Model
{
    use SoftDeletes;

    public const CATEGORY_FOREX = 'FOREX';
    public const CATEGORY_CRYPTO = 'CRYPTO';
    public const CATEGORY_DERIV = 'DERIV';
    public const CATEGORY_STOCKS = 'STOCKS';

    public const REGION_SRI_LANKA = 'SRI_LANKA';
    public const REGION_INDIA = 'INDIA';
    public const REGION_INTERNATIONAL = 'INTERNATIONAL';

    public const DIRECTION_BUY = 'BUY';
    public const DIRECTION_SELL = 'SELL';

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';
    public const STATUS_CLOSED = 'CLOSED';

    public const OUTCOME_WIN = 'WIN';
    public const OUTCOME_LOSS = 'LOSS';
    public const OUTCOME_BREAKEVEN = 'BREAKEVEN';
    public const OUTCOME_CANCELLED = 'CANCELLED';
    public const OUTCOME_EXPIRED = 'EXPIRED';

    public const TIMEFRAMES = [
        'M1',
        'M5',
        'M15',
        'M30',
        'H1',
        'H4',
        'D1',
        'W1',
        'MN1',
    ];

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
        'current_stop_loss',
        'entry_triggered_at',
        'entry_price_actual',
        'status',
        'published_at',
        'closed_at',
        'close_outcome',
        'close_price',
        'close_note',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'entry_price_low' => 'decimal:10',
            'entry_price_high' => 'decimal:10',
            'stop_loss' => 'decimal:10',
            'current_stop_loss' => 'decimal:10',
            'entry_price_actual' => 'decimal:10',
            'close_price' => 'decimal:10',
            'chart_image_size_bytes' => 'integer',
            'entry_triggered_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TradeIdea $tradeIdea): void {
            if (
                $tradeIdea->uuid === null
                || trim((string) $tradeIdea->uuid) === ''
            ) {
                $tradeIdea->uuid = Str::uuid()->toString();
            }
        });
    }

    public function targets(): HasMany
    {
        return $this->hasMany(
            TradeIdeaTarget::class
        )->orderBy(
            'sort_order'
        );
    }

    public function updates(): HasMany
    {
        return $this->hasMany(
            TradeIdeaUpdate::class
        )->latest(
            'id'
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
            );
    }

    public function scopeLatestFirst(
        Builder $query
    ): Builder {
        return $query
            ->orderByDesc(
                'published_at'
            )
            ->orderByDesc(
                'id'
            );
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function hasChartImage(): bool
    {
        return $this->chart_image_disk !== null
            && $this->chart_image_path !== null;
    }

    public function hasTradeStructure(): bool
    {
        return $this->entry_price_low !== null
            || $this->entry_price_high !== null
            || $this->stop_loss !== null
            || $this->targets()->exists();
    }

    public function displayEntryZone(): string
    {
        if (
            $this->entry_price_low === null
            && $this->entry_price_high === null
        ) {
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
