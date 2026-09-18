<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TradingFloorFloor extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'uuid', 'code', 'name', 'description', 'status', 'capacity', 'sort_order',
        'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $floor): void {
            $floor->uuid ??= (string) Str::uuid();
            $floor->code = strtoupper(trim((string) $floor->code));
            $floor->status = strtoupper((string) ($floor->status ?: self::STATUS_ACTIVE));
        });

        static::updating(function (self $floor): void {
            $floor->code = strtoupper(trim((string) $floor->code));
            $floor->status = strtoupper((string) $floor->status);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function desks(): HasMany
    {
        return $this->hasMany(TradingFloorDesk::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TradingFloorMembership::class);
    }
}
