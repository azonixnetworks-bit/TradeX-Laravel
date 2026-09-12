<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'admin_user_id',
        'session_id',
        'device_name',
        'device_type',
        'browser',
        'platform',
        'ip_address',
        'user_agent',
        'login_at',
        'last_seen_at',
        'logged_out_at',
        'revoked_at',
        'revoked_reason',
    ];

    protected function casts(): array
    {
        return [
            'login_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && $this->logged_out_at === null;
    }
}
