<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminSession extends Model
{
    use HasFactory;

    public const ACTIVITY_ONLINE = 'ONLINE';

    public const ACTIVITY_RECENT = 'RECENTLY_ACTIVE';

    public const ACTIVITY_OFFLINE = 'OFFLINE';

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

    public function lifecycleStatus(): string
    {
        if ($this->revoked_at !== null) {
            return 'REVOKED';
        }

        if ($this->logged_out_at !== null) {
            return 'LOGGED_OUT';
        }

        return 'ACTIVE';
    }

    public function activityStatus(): string
    {
        if (! $this->isActive() || $this->last_seen_at === null) {
            return self::ACTIVITY_OFFLINE;
        }

        if ($this->last_seen_at->gte(now()->subMinutes(2))) {
            return self::ACTIVITY_ONLINE;
        }

        if ($this->last_seen_at->gte(now()->subMinutes(15))) {
            return self::ACTIVITY_RECENT;
        }

        return self::ACTIVITY_OFFLINE;
    }
}
