<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TradingFloorDesk extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_MAINTENANCE = 'MAINTENANCE';
    public const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'uuid', 'trading_floor_floor_id', 'code', 'name', 'zone', 'status', 'notes',
        'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $desk): void {
            $desk->uuid ??= (string) Str::uuid();
            $desk->code = strtoupper(trim((string) $desk->code));
            $desk->status = strtoupper((string) ($desk->status ?: self::STATUS_ACTIVE));
        });

        static::updating(function (self $desk): void {
            $desk->code = strtoupper(trim((string) $desk->code));
            $desk->status = strtoupper((string) $desk->status);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(TradingFloorFloor::class, 'trading_floor_floor_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TradingFloorMembership::class);
    }
}
