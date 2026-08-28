<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CopyTradeOnboardingIntent extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'broker_market_id',
        'referral_url_snapshot',
        'opened_at',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $intent): void {
            if (blank($intent->uuid)) {
                $intent->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function brokerMarket(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerMarket::class,
            'broker_market_id'
        );
    }

    public function brokerAccount(): HasOne
    {
        return $this->hasOne(
            CopyTradeBrokerAccount::class,
            'onboarding_intent_id'
        );
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null
            || $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isUsableBy(User $user): bool
    {
        return $this->user_id === $user->id
            && ! $this->isExpired()
            && ! $this->isConsumed();
    }
}
