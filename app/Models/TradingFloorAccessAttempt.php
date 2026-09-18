<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorAccessAttempt extends Model
{
    protected $fillable = [
        'uuid',
        'trading_floor_access_device_id',
        'device_code',
        'device_event_id',
        'trading_floor_client_id',
        'trading_floor_access_credential_id',
        'credential_type',
        'credential_fingerprint',
        'direction',
        'decision',
        'reason_code',
        'reason',
        'occurred_at',
        'response_json',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'response_json' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attempt): void {
            $attempt->uuid ??= (string) Str::uuid();
            $attempt->device_code = strtoupper(trim((string) $attempt->device_code));
            $attempt->credential_type = strtoupper(trim((string) $attempt->credential_type));
            $attempt->direction = strtoupper(trim((string) $attempt->direction));
            $attempt->decision = strtoupper(trim((string) $attempt->decision));
            $attempt->reason_code = strtoupper(trim((string) $attempt->reason_code));
            $attempt->occurred_at ??= now();
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TradingFloorAccessDevice::class, 'trading_floor_access_device_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(TradingFloorClient::class, 'trading_floor_client_id');
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(TradingFloorAccessCredential::class, 'trading_floor_access_credential_id');
    }
}
