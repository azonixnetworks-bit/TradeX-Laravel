<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TradingFloorAccessDevice extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'uuid',
        'device_code',
        'name',
        'floor_code',
        'token_hash',
        'status',
        'last_seen_at',
        'last_ip',
        'revoked_at',
        'revocation_reason',
        'created_by_admin_id',
        'revoked_by_admin_id',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $device): void {
            $device->uuid ??= (string) Str::uuid();
            $device->device_code = strtoupper(trim((string) $device->device_code));
            $device->status = strtoupper(trim((string) ($device->status ?: self::STATUS_ACTIVE)));
        });

        static::updating(function (self $device): void {
            $device->device_code = strtoupper(trim((string) $device->device_code));
            $device->status = strtoupper(trim((string) $device->status));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }

    public function revokedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'revoked_by_admin_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TradingFloorAccessAttempt::class, 'trading_floor_access_device_id');
    }
}
