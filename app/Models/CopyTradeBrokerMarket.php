<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class CopyTradeBrokerMarket extends Model
{
    protected $fillable = [
        'uuid',
        'broker_id',
        'market_category',
        'referral_url',
        'required_currency',
        'live_account_only',
        'is_active',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'live_account_only' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $market): void {
            if (blank($market->uuid)) {
                $market->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBroker::class,
            'broker_id'
        );
    }

    public function onboardingIntents(): HasMany
    {
        return $this->hasMany(
            CopyTradeOnboardingIntent::class,
            'broker_market_id'
        );
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(
            CopyTradeBrokerAccount::class,
            'broker_market_id'
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

    public function isAvailableForOnboarding(): bool
    {
        return $this->is_active
            && $this->broker?->is_active === true;
    }
}
