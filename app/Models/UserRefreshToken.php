<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',

        'user_id',
        'user_device_id',

        'token_hash',
        'family_uuid',

        'expires_at',
        'last_used_at',

        'revoked_at',
        'revoke_reason',
    ];


    protected $hidden = [
        'token_hash',
    ];


    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }


    public function device(): BelongsTo
    {
        return $this->belongsTo(
            UserDevice::class,
            'user_device_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(
        Builder $query
    ): Builder {

        return $query
            ->whereNull('revoked_at')
            ->where(
                'expires_at',
                '>',
                now()
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at
            ->isPast();
    }


    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }


    public function isUsable(): bool
    {
        return ! $this->isExpired()
            && ! $this->isRevoked();
    }
}