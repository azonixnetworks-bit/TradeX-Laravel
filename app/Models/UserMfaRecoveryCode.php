<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMfaRecoveryCode extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'code_hash',
        'issued_at',
        'used_at',
        'revoked_at',
        'used_ip',
        'used_user_agent',
    ];

    protected $hidden = [
        'code_hash',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null
            && $this->revoked_at === null;
    }
}
