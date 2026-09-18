<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminDevice extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected $attributes = ['session_version' => 1];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
