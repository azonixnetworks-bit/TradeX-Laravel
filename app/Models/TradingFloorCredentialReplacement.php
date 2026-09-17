<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TradingFloorCredentialReplacement extends Model
{
    protected $fillable = [
        'uuid',
        'trading_floor_client_id',
        'old_credential_id',
        'new_credential_id',
        'replacement_reason',
        'replaced_at',
        'replaced_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'replaced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $replacement): void {
            $replacement->uuid ??= (string) Str::uuid();
            $replacement->replaced_at ??= now();
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

    public function oldCredential(): BelongsTo
    {
        return $this->belongsTo(TradingFloorAccessCredential::class, 'old_credential_id');
    }

    public function newCredential(): BelongsTo
    {
        return $this->belongsTo(TradingFloorAccessCredential::class, 'new_credential_id');
    }

    public function replacedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'replaced_by_admin_id');
    }
}
