<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSocialAccount extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'provider_user_id',
        'provider_email',
        'linked_at',
        'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
