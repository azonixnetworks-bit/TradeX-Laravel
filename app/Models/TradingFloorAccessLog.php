<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorAccessLog extends Model
{
    public const DIRECTION_ENTRY = 'ENTRY';
    public const DIRECTION_EXIT = 'EXIT';

    protected $fillable = [
        'uuid', 'trading_floor_client_id', 'trading_floor_access_credential_id', 'direction',
        'method', 'device_code', 'decision', 'decision_reason', 'accessed_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->uuid ??= (string) Str::uuid();
            $log->direction = strtoupper((string) $log->direction);
            $log->method = strtoupper((string) $log->method);
            $log->decision = strtoupper((string) ($log->decision ?: 'GRANTED'));
        });
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
