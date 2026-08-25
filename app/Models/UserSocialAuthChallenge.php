<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSocialAuthChallenge extends Model
{
    protected $fillable = [
        'uuid',
        'provider',
        'purpose',
        'nonce_hash',
        'expires_at',
        'consumed_at',
        'request_ip',
    ];

    protected $hidden = ['nonce_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
