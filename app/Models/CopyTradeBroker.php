<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class CopyTradeBroker extends Model
{
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'logo_disk',
        'logo_path',
        'logo_mime_type',
        'logo_size_bytes',
        'is_active',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'logo_size_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $broker): void {
            if (blank($broker->uuid)) {
                $broker->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function markets(): HasMany
    {
        return $this->hasMany(
            CopyTradeBrokerMarket::class,
            'broker_id'
        );
    }

    public function onboardingIntents(): HasManyThrough
    {
        return $this->hasManyThrough(
            CopyTradeOnboardingIntent::class,
            CopyTradeBrokerMarket::class,
            'broker_id',
            'broker_market_id'
        );
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(
            CopyTradeBrokerAccount::class,
            'broker_id'
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

    public function hasLogo(): bool
    {
        return filled($this->logo_disk)
            && filled($this->logo_path);
    }
}
