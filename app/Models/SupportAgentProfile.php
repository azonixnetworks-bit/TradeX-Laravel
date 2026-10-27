<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportAgentProfile extends Model
{
    protected $fillable = ['admin_user_id', 'enabled', 'availability', 'capacity', 'heartbeat_at'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'heartbeat_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'admin_user_id');
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(SupportDepartment::class, 'support_agent_departments', 'agent_profile_id', 'department_id');
    }

    public function isOnline(): bool
    {
        return $this->enabled && $this->availability === 'online' && $this->heartbeat_at?->gte(now()->subSeconds((int) config('support.agent_presence_ttl_seconds', 90)));
    }
}
