<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorMembershipChange extends Model
{
    public const TYPE_RENEWAL = 'RENEWAL';
    public const TYPE_PACKAGE_CHANGE = 'PACKAGE_CHANGE';
    public const TYPE_DESK_TRANSFER = 'DESK_TRANSFER';
    public const TYPE_EXPIRED = 'EXPIRED';
    public const TYPE_AUTO_RENEWAL_CREATED = 'AUTO_RENEWAL_CREATED';

    protected $fillable = [
        'uuid', 'trading_floor_membership_id', 'change_type', 'old_package_id', 'new_package_id',
        'old_floor_id', 'new_floor_id', 'old_desk_id', 'new_desk_id', 'old_expires_at',
        'new_expires_at', 'metadata', 'reason', 'changed_by_admin_id', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'old_expires_at' => 'date',
            'new_expires_at' => 'date',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $change): void {
            $change->uuid ??= (string) Str::uuid();
            $change->change_type = strtoupper((string) $change->change_type);
            $change->occurred_at ??= now();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(TradingFloorMembership::class, 'trading_floor_membership_id');
    }

    public function changedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'changed_by_admin_id');
    }
}
