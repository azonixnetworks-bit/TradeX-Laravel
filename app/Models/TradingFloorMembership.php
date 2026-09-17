<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TradingFloorMembership extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const PAYMENT_PENDING = 'PENDING';
    public const PAYMENT_PARTIAL = 'PARTIALLY_PAID';
    public const PAYMENT_PAID = 'PAID';
    public const PAYMENT_WAIVED = 'WAIVED';

    protected $fillable = [
        'uuid', 'membership_number', 'trading_floor_client_id', 'trading_floor_package_id',
        'trading_floor_floor_id', 'trading_floor_desk_id', 'starts_at', 'expires_at', 'due_date',
        'status', 'price_amount', 'currency', 'billing_cycle', 'payment_status', 'paid_amount',
        'balance_amount', 'auto_renew', 'renewed_from_membership_id', 'activated_at',
        'cancelled_at', 'notes', 'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'expires_at' => 'date',
            'due_date' => 'date',
            'price_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'auto_renew' => 'boolean',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $membership): void {
            $membership->uuid ??= (string) Str::uuid();
            $membership->status = strtoupper((string) ($membership->status ?: self::STATUS_PENDING));
            $membership->payment_status = strtoupper((string) ($membership->payment_status ?: self::PAYMENT_PENDING));
            $membership->currency = strtoupper((string) ($membership->currency ?: 'LKR'));
            $membership->billing_cycle = strtoupper((string) ($membership->billing_cycle ?: 'MONTHLY'));
        });

        static::updating(function (self $membership): void {
            $membership->status = strtoupper((string) $membership->status);
            $membership->payment_status = strtoupper((string) $membership->payment_status);
            $membership->currency = strtoupper((string) $membership->currency);
            $membership->billing_cycle = strtoupper((string) $membership->billing_cycle);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TradingFloorClient::class, 'trading_floor_client_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(TradingFloorPackage::class, 'trading_floor_package_id');
    }

    public function floor(): BelongsTo
    {
        return $this->belongsTo(TradingFloorFloor::class, 'trading_floor_floor_id');
    }

    public function desk(): BelongsTo
    {
        return $this->belongsTo(TradingFloorDesk::class, 'trading_floor_desk_id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_membership_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'renewed_from_membership_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TradingFloorMembershipPayment::class, 'trading_floor_membership_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(TradingFloorMembershipChange::class, 'trading_floor_membership_id');
    }
}
