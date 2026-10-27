<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportChatSession extends Model
{
    use HasUuids;

    public const ACTIVE_STATES = ['queued', 'assigned', 'active'];

    protected $fillable = ['ticket_id', 'user_id', 'active_user_id', 'accepted_by_admin_id', 'state', 'idempotency_key', 'queued_at', 'accepted_at', 'last_customer_heartbeat_at', 'last_agent_heartbeat_at', 'ended_at', 'closure_reason'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'accepted_at' => 'datetime', 'last_customer_heartbeat_at' => 'datetime', 'last_agent_heartbeat_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function acceptedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'accepted_by_admin_id');
    }
}
