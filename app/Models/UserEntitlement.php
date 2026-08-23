<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserEntitlement extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_REVOKED = 'REVOKED';

    protected $fillable = [
        'uuid',
        'user_id',
        'product_code',
        'status',
        'activated_at',
        'payment_id',
        'revoked_at',
        'revoke_reason',
    ];

    protected $hidden = [
        'revoke_reason',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'revoke_reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entitlement): void {
            if (blank($entitlement->uuid)) {
                $entitlement->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
