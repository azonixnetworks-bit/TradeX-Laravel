<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorAccessCredential extends Model
{
    public const TYPE_NFC = 'NFC';
    public const TYPE_FINGERPRINT = 'FINGERPRINT';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'uuid',
        'trading_floor_client_id',
        'credential_type',
        'credential_reference',
        'device_code',
        'status',
        'issued_at',
        'revoked_at',
        'revocation_reason',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $credential): void {
            $credential->uuid ??= (string) Str::uuid();
            $credential->credential_type = strtoupper(trim((string) $credential->credential_type));
            $credential->status = strtoupper(trim((string) ($credential->status ?: self::STATUS_ACTIVE)));
            $credential->issued_at ??= now();
        });

        static::updating(function (self $credential): void {
            $credential->credential_type = strtoupper(trim((string) $credential->credential_type));
            $credential->status = strtoupper(trim((string) $credential->status));
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

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by_admin_id');
    }
}
