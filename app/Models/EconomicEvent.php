<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class EconomicEvent extends Model
{
    public const IMPACT_HIGH = 'HIGH';

    public const IMPACT_MEDIUM = 'MEDIUM';

    public const IMPACT_LOW = 'LOW';

    public const BIAS_BULLISH = 'BULLISH';

    public const BIAS_BEARISH = 'BEARISH';

    public const BIAS_NEUTRAL = 'NEUTRAL';

    public const VOLATILITY_LOW = 'LOW';

    public const VOLATILITY_MEDIUM = 'MEDIUM';

    public const VOLATILITY_HIGH = 'HIGH';

    public const VOLATILITY_EXTREME = 'EXTREME';

    public const STATUS_SCHEDULED = 'SCHEDULED';

    public const STATUS_LIVE = 'LIVE';

    public const STATUS_RELEASED = 'RELEASED';

    public const STATUS_REVISED = 'REVISED';

    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'uuid',
        'economic_news_source_id',
        'provider_event_id',
        'origin',
        'title',
        'category',
        'country_code',
        'currency',
        'impact',
        'scheduled_at_utc',
        'source_timezone',
        'status',
        'actual',
        'forecast',
        'previous',
        'revised_previous',
        'bias',
        'confidence_score',
        'volatility',
        'reason',
        'analysis',
        'is_published',
        'published_at',
        'released_at',
        'last_synced_at',
        'source_payload_json',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at_utc' => 'datetime',
            'confidence_score' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'released_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'source_payload_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (blank($event->uuid)) {
                $event->uuid = (string) Str::uuid();
            }

            self::normalize($event);
        });

        static::updating(function (self $event): void {
            self::normalize($event);
        });
    }

    private static function normalize(self $event): void
    {
        foreach (['origin', 'country_code', 'currency', 'impact', 'status', 'bias', 'volatility'] as $field) {
            if ($event->{$field} !== null) {
                $event->{$field} = strtoupper(trim((string) $event->{$field}));
            }
        }
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EconomicNewsSource::class, 'economic_news_source_id');
    }

    public function pairs(): HasMany
    {
        return $this->hasMany(EconomicEventPair::class)->orderBy('sort_order')->orderBy('symbol');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(EconomicEventUpdate::class)->orderByDesc('published_at');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(EconomicEventOverride::class)->latest('created_at');
    }

    public function activeOverride(): HasOne
    {
        return $this->hasOne(EconomicEventOverride::class)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latestOfMany();
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function updatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'updated_by_admin_id');
    }

    /**
     * @return array{impact:string,status:string,bias:string,confidence_score:int,volatility:string,reason:?string,analysis:?string,manual_override:bool,override_uuid:?string}
     */
    public function effectiveIntelligence(): array
    {
        $override = $this->activeOverride;

        return [
            'impact' => (string) ($override?->impact ?? $this->impact),
            'status' => (string) ($override?->status ?? $this->status),
            'bias' => (string) ($override?->bias ?? $this->bias),
            'confidence_score' => (int) ($override?->confidence_score ?? $this->confidence_score),
            'volatility' => (string) ($override?->volatility ?? $this->volatility),
            'reason' => $override?->reason ?? $this->reason,
            'analysis' => $override?->analysis ?? $this->analysis,
            'manual_override' => $override !== null,
            'override_uuid' => $override?->uuid,
        ];
    }
}
