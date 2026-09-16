<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EconomicEventOverride extends Model
{
    protected $fillable = [
        'uuid',
        'economic_event_id',
        'impact',
        'status',
        'bias',
        'confidence_score',
        'volatility',
        'reason',
        'analysis',
        'override_note',
        'is_active',
        'starts_at',
        'expires_at',
        'revoked_at',
        'overridden_by_admin_id',
        'revoked_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'confidence_score' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $override): void {
            if (blank($override->uuid)) {
                $override->uuid = (string) Str::uuid();
            }
        });

        static::saving(function (self $override): void {
            foreach (['impact', 'status', 'bias', 'volatility'] as $field) {
                if ($override->{$field} !== null) {
                    $override->{$field} = strtoupper(trim((string) $override->{$field}));
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

    public function overriddenByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'overridden_by_admin_id');
    }

    public function revokedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'revoked_by_admin_id');
    }

    public function isEffectiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $this->is_active || $this->revoked_at !== null) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isAfter($at)) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isAfter($at);
    }
}
