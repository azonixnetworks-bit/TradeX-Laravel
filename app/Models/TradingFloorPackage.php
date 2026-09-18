<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TradingFloorPackage extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'uuid', 'code', 'name', 'description', 'currency', 'price_amount', 'billing_cycle',
        'duration_days', 'commission_type', 'commission_rate', 'fixed_commission_amount',
        'features', 'status', 'sort_order', 'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'duration_days' => 'integer',
            'commission_rate' => 'decimal:4',
            'fixed_commission_amount' => 'decimal:2',
            'features' => 'array',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $package): void {
            $package->uuid ??= (string) Str::uuid();
            $package->code = strtoupper(trim((string) $package->code));
            $package->currency = strtoupper((string) ($package->currency ?: 'LKR'));
            $package->billing_cycle = strtoupper((string) ($package->billing_cycle ?: 'MONTHLY'));
            $package->status = strtoupper((string) ($package->status ?: self::STATUS_ACTIVE));
            $package->commission_type = $package->commission_type ? strtoupper((string) $package->commission_type) : null;
        });

        static::updating(function (self $package): void {
            $package->code = strtoupper(trim((string) $package->code));
            $package->currency = strtoupper((string) $package->currency);
            $package->billing_cycle = strtoupper((string) $package->billing_cycle);
            $package->status = strtoupper((string) $package->status);
            $package->commission_type = $package->commission_type ? strtoupper((string) $package->commission_type) : null;
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TradingFloorMembership::class);
    }
}
