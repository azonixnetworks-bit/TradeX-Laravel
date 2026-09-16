<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EconomicEventUpdate extends Model
{
    public const TYPE_PRE_EVENT = 'PRE_EVENT';

    public const TYPE_LIVE = 'LIVE';

    public const TYPE_RELEASE = 'RELEASE';

    public const TYPE_REVISION = 'REVISION';

    public const TYPE_ANALYSIS = 'ANALYSIS';

    protected $fillable = [
        'uuid',
        'economic_event_id',
        'economic_news_source_id',
        'provider_update_id',
        'update_type',
        'message',
        'actual',
        'forecast',
        'previous',
        'bias',
        'confidence_score',
        'volatility',
        'is_published',
        'published_at',
        'source_payload_json',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'source_payload_json' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $update): void {
            if (blank($update->uuid)) {
                $update->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $update): void {
            foreach (['update_type', 'bias', 'volatility'] as $field) {
                if ($update->{$field} !== null) {
                    $update->{$field} = strtoupper(trim((string) $update->{$field}));
                }
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EconomicEvent::class, 'economic_event_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(EconomicNewsSource::class, 'economic_news_source_id');
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
