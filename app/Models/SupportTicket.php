<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportTicket extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const STATUSES = ['open', 'in_progress', 'waiting_for_customer', 'resolved', 'closed'];

    public const PRIORITIES = ['low', 'normal', 'high', 'critical'];

    protected $fillable = [
        'public_reference', 'user_id', 'department_id', 'category_id', 'assigned_admin_id',
        'source', 'subject', 'description', 'intake_data', 'related_type', 'related_reference',
        'requested_urgency', 'priority', 'status', 'idempotency_key', 'first_response_due_at',
        'resolution_due_at', 'first_responded_at', 'resolved_at', 'closed_at', 'reopened_at',
        'resolution_cycle',
    ];

    protected function casts(): array
    {
        return [
            'intake_data' => 'array',
            'first_response_due_at' => 'datetime', 'resolution_due_at' => 'datetime',
            'first_responded_at' => 'datetime', 'resolved_at' => 'datetime',
            'closed_at' => 'datetime', 'reopened_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(SupportDepartment::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id');
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(SupportChatSession::class, 'ticket_id');
    }

    public function latestChatSession(): HasOne
    {
        return $this->hasOne(SupportChatSession::class, 'ticket_id')->latestOfMany();
    }
}
