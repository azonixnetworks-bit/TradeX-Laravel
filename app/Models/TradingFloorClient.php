<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TradingFloorClient extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    public const ACCESS_OVERRIDE_AUTO = 'AUTO';
    public const ACCESS_OVERRIDE_FORCE_ALLOW = 'FORCE_ALLOW';
    public const ACCESS_OVERRIDE_FORCE_DENY = 'FORCE_DENY';

    protected $fillable = [
        'uuid', 'client_code', 'user_id', 'full_name', 'mobile', 'whatsapp_number', 'email',
        'identity_type', 'identity_number', 'date_of_birth', 'address', 'emergency_contact_name',
        'emergency_contact_phone', 'joined_at', 'floor_code', 'desk_code', 'package_code',
        'commission_type', 'commission_rate', 'fixed_commission_amount', 'commission_currency',
        'billing_cycle', 'grace_days', 'status',
        'access_override_mode', 'access_override_until', 'access_override_reason', 'access_override_by_admin_id',
        'created_by_admin_id', 'updated_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joined_at' => 'date',
            'commission_rate' => 'decimal:4',
            'fixed_commission_amount' => 'decimal:2',
            'grace_days' => 'integer',
            'access_override_until' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $client): void {
            $client->uuid ??= (string) Str::uuid();
            $client->status = strtoupper((string) ($client->status ?: self::STATUS_ACTIVE));
            $client->commission_type = strtoupper((string) ($client->commission_type ?: 'PERCENTAGE'));
            $client->billing_cycle = strtoupper((string) ($client->billing_cycle ?: 'MONTHLY'));
            $client->commission_currency = strtoupper((string) ($client->commission_currency ?: 'LKR'));
            $client->access_override_mode = strtoupper((string) ($client->access_override_mode ?: self::ACCESS_OVERRIDE_AUTO));
        });

        static::updating(function (self $client): void {
            $client->status = strtoupper((string) $client->status);
            $client->commission_type = strtoupper((string) $client->commission_type);
            $client->billing_cycle = strtoupper((string) $client->billing_cycle);
            $client->commission_currency = strtoupper((string) $client->commission_currency);
            $client->access_override_mode = strtoupper((string) ($client->access_override_mode ?: self::ACCESS_OVERRIDE_AUTO));
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

    public function accessCredentials(): HasMany
    {
        return $this->hasMany(TradingFloorAccessCredential::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(TradingFloorAccessLog::class);
    }

    public function accessAttempts(): HasMany
    {
        return $this->hasMany(TradingFloorAccessAttempt::class);
    }

    public function commissionInvoices(): HasMany
    {
        return $this->hasMany(TradingFloorCommissionInvoice::class);
    }

    public function accessOverrideByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'access_override_by_admin_id');
    }
}
