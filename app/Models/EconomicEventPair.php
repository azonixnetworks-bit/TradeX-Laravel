<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EconomicEventPair extends Model
{
    protected $fillable = [
        'economic_event_id',
        'market',
        'symbol',
        'bias',
        'confidence_score',
        'volatility',
        'reason',
        'mapping_source',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $pair): void {
            foreach (['market', 'symbol', 'bias', 'volatility', 'mapping_source'] as $field) {
                if ($pair->{$field} !== null) {
                    $pair->{$field} = strtoupper(trim((string) $pair->{$field}));
                }
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EconomicEvent::class, 'economic_event_id');
    }
}
