<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PushDeviceToken extends Model
{
    public const PROVIDER_FCM = 'FCM';
    public const PROVIDER_APNS = 'APNS';

    protected $fillable = [
        'uuid',
        'user_id',
        'user_device_id',
        'provider',
        'platform',
        'token_hash',
        'token_ciphertext',
        'is_active',
        'registered_at',
        'last_seen_at',
        'revoked_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'token_hash',
        'token_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'registered_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $token): void {
            if (blank($token->uuid)) {
                $token->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class);
    }

    public function revealToken(): string
    {
        return Crypt::decryptString((string) $this->token_ciphertext);
    }

    public function isUsable(): bool
    {
        return $this->is_active
            && $this->revoked_at === null
            && $this->invalidated_at === null;
    }
}
