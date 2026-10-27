<?php

namespace App\Services\Support;

use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportCategory;
use App\Models\SupportChatSession;
use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(
        private readonly SupportSlaClock $clock,
        private readonly SupportNotificationOutbox $outbox,
    ) {}

    public function create(User $user, array $data, string $idempotencyKey, string $source = 'ticket'): SupportTicket
    {
        return DB::transaction(function () use ($user, $data, $idempotencyKey, $source): SupportTicket {
            $existing = SupportTicket::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }

            $category = SupportCategory::query()->where('uuid', $data['category_uuid'])->where('enabled', true)->firstOrFail();
            $priority = $this->calculatePriority($category, $data['requested_urgency'] ?? null);
            $policy = SupportSlaPolicy::query()
                ->where('priority', $priority)
                ->where('enabled', true)
                ->where(fn ($query) => $query->where('department_id', $category->department_id)->orWhereNull('department_id'))
                ->orderByRaw('department_id is null')
                ->first();
            $now = CarbonImmutable::now('UTC');
            $timezone = $category->department?->timezone ?: config('support.timezone');

            $ticket = SupportTicket::create([
                'public_reference' => $this->nextReference(),
                'user_id' => $user->id,
                'department_id' => $category->department_id,
                'category_id' => $category->id,
                'source' => $source,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'intake_data' => $data['intake'] ?? [],
                'requested_urgency' => $data['requested_urgency'] ?? null,
                'priority' => $priority,
                'status' => 'open',
                'idempotency_key' => $idempotencyKey,
                'first_response_due_at' => $policy ? $this->clock->addBusinessMinutes($now, $policy->first_response_minutes, $timezone) : null,
                'resolution_due_at' => $policy ? $this->clock->addBusinessMinutes($now, $policy->resolution_minutes, $timezone) : null,
            ]);

            $ticket->messages()->create([
                'sender_type' => 'customer',
                'sender_user_id' => $user->id,
                'visibility' => 'public',
                'body' => $data['description'],
                'client_idempotency_key' => 'initial:'.$idempotencyKey,
            ]);

            $this->event($ticket, 'ticket.created', 'customer', $user->id, ['source' => $source]);
            $this->outbox->customer($ticket, 'ticket-received', ['reference' => $ticket->public_reference, 'subject' => $ticket->subject], 'created');
            $this->outbox->queueTeam($ticket);

            return $ticket->fresh(['category', 'department', 'assignedAdmin']);
        }, 3);
    }

    public function addCustomerMessage(SupportTicket $ticket, User $user, string $body, string $key): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $user, $body, $key): SupportTicketMessage {
            if (in_array($ticket->status, ['resolved', 'closed'], true)) {
                throw ValidationException::withMessages(['ticket' => 'Reopen this ticket before replying.']);
            }

            $message = SupportTicketMessage::firstOrCreate(
                ['ticket_id' => $ticket->id, 'sender_type' => 'customer', 'client_idempotency_key' => $key],
                ['sender_user_id' => $user->id, 'visibility' => 'public', 'body' => $body]
            );

            if ($message->wasRecentlyCreated) {
                if ($ticket->status === 'waiting_for_customer') {
                    $ticket->update(['status' => 'in_progress']);
                }
                $this->event($ticket, 'message.customer_created', 'customer', $user->id, ['message_uuid' => $message->uuid]);
                if ($ticket->assigned_admin_id) {
                    $admin = AdminUser::query()->find($ticket->assigned_admin_id);
                    if ($admin) {
                        $this->outbox->admin($ticket, $admin, 'customer-reply', ['reference' => $ticket->public_reference, 'body' => Str::limit($body, 500)], 'customer-message:'.$message->id);
                    }
                }
            }

            return $message;
        });
    }

    public function addAdminMessage(SupportTicket $ticket, AdminUser $admin, string $body, string $visibility): SupportTicketMessage
    {
        return DB::transaction(function () use ($ticket, $admin, $body, $visibility): SupportTicketMessage {
            $message = $ticket->messages()->create([
                'sender_type' => 'admin', 'sender_admin_id' => $admin->id,
                'visibility' => $visibility, 'body' => $body,
            ]);
            if ($visibility === 'public') {
                $ticket->forceFill([
                    'status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status,
                    'first_responded_at' => $ticket->first_responded_at ?: now(),
                ])->save();
                $this->outbox->customer($ticket, 'admin-reply', ['reference' => $ticket->public_reference, 'body' => Str::limit($body, 500)], 'message:'.$message->id);
            }
            $this->event($ticket, 'message.'.$visibility.'_created', 'admin', $admin->id, ['message_uuid' => $message->uuid]);

            return $message;
        });
    }

    public function startChat(User $user, array $data, string $key): SupportChatSession
    {
        try {
            return DB::transaction(function () use ($user, $data, $key): SupportChatSession {
                $existing = SupportChatSession::query()->where('user_id', $user->id)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $existing;
                }

                $active = SupportChatSession::query()->where('user_id', $user->id)->whereIn('state', SupportChatSession::ACTIVE_STATES)->lockForUpdate()->first();
                if ($active) {
                    return $active;
                }

                $ticket = $this->create($user, $data, 'chat-ticket:'.$key, 'chat');
                $chat = SupportChatSession::create([
                    'ticket_id' => $ticket->id, 'user_id' => $user->id, 'active_user_id' => $user->id, 'state' => 'queued',
                    'idempotency_key' => $key, 'queued_at' => now(), 'last_customer_heartbeat_at' => now(),
                ]);
                $this->event($ticket, 'chat.queued', 'customer', $user->id, ['chat_uuid' => $chat->uuid]);
                $this->assignAvailableAgent($ticket, $chat);

                return $chat->fresh(['ticket.assignedAdmin']);
            }, 3);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $active = SupportChatSession::query()
                ->where('active_user_id', $user->id)
                ->whereIn('state', SupportChatSession::ACTIVE_STATES)
                ->first();

            if ($active) {
                return $active;
            }

            throw $exception;
        }
    }

    public function claim(SupportTicket $ticket, AdminUser $admin): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $admin): SupportTicket {
            $locked = SupportTicket::query()->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($locked->assigned_admin_id && $locked->assigned_admin_id !== $admin->id) {
                throw ValidationException::withMessages(['ticket' => 'This ticket is already assigned.']);
            }
            $locked->update(['assigned_admin_id' => $admin->id, 'status' => 'in_progress']);
            $locked->latestChatSession()->where('state', 'queued')->update([
                'state' => 'assigned', 'accepted_by_admin_id' => $admin->id,
                'accepted_at' => now(), 'last_agent_heartbeat_at' => now(),
            ]);
            $this->event($locked, 'ticket.claimed', 'admin', $admin->id);
            $this->outbox->customer($locked, 'ticket-assigned', ['reference' => $locked->public_reference], 'assigned:'.$admin->id);

            return $locked;
        }, 3);
    }

    private function assignAvailableAgent(SupportTicket $ticket, SupportChatSession $chat): void
    {
        $cutoff = now()->subSeconds((int) config('support.agent_presence_ttl_seconds'));
        $profiles = SupportAgentProfile::query()
            ->with(['admin', 'departments'])
            ->where('enabled', true)
            ->where('availability', 'online')
            ->where('heartbeat_at', '>=', $cutoff)
            ->whereHas('admin', fn ($query) => $query->where('is_active', true))
            ->lockForUpdate()
            ->get()
            ->filter(function (SupportAgentProfile $profile) use ($ticket): bool {
                if ($profile->departments->isNotEmpty() && ! $profile->departments->contains('id', $ticket->department_id)) {
                    return false;
                }

                $activeChats = SupportChatSession::query()
                    ->where('accepted_by_admin_id', $profile->admin_user_id)
                    ->whereIn('state', ['assigned', 'active'])
                    ->count();

                return $activeChats < $profile->capacity;
            })
            ->sortBy(function (SupportAgentProfile $profile): int {
                return SupportChatSession::query()
                    ->where('accepted_by_admin_id', $profile->admin_user_id)
                    ->whereIn('state', ['assigned', 'active'])
                    ->count();
            })
            ->first();

        if (! $profiles) {
            return;
        }

        $ticket->update(['assigned_admin_id' => $profiles->admin_user_id, 'status' => 'in_progress']);
        $chat->update([
            'state' => 'assigned',
            'accepted_by_admin_id' => $profiles->admin_user_id,
            'accepted_at' => now(),
            'last_agent_heartbeat_at' => $profiles->heartbeat_at,
        ]);
        $this->event($ticket, 'chat.auto_assigned', 'system', null, ['admin_id' => $profiles->admin_user_id]);
        $this->outbox->customer($ticket, 'ticket-assigned', ['reference' => $ticket->public_reference], 'chat-assigned:'.$profiles->admin_user_id);
    }

    public function transition(SupportTicket $ticket, AdminUser|User $actor, string $status): SupportTicket
    {
        $allowed = [
            'open' => ['in_progress', 'closed'],
            'in_progress' => ['waiting_for_customer', 'resolved', 'closed'],
            'waiting_for_customer' => ['in_progress', 'resolved', 'closed'],
            'resolved' => ['in_progress', 'closed'],
            'closed' => ['in_progress'],
        ];
        if (! in_array($status, $allowed[$ticket->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Invalid transition from {$ticket->status} to {$status}."]);
        }

        $updates = ['status' => $status];
        if ($status === 'resolved') {
            $updates['resolved_at'] = now();
            $updates['resolution_cycle'] = $ticket->resolution_cycle + 1;
        }
        if ($status === 'closed') {
            $updates['closed_at'] = now();
        }
        if ($status === 'in_progress' && in_array($ticket->status, ['resolved', 'closed'], true)) {
            $updates['reopened_at'] = now();
        }
        $ticket->update($updates);
        $this->event($ticket, 'ticket.status_changed', $actor instanceof AdminUser ? 'admin' : 'customer', $actor->id, ['from' => $ticket->getOriginal('status'), 'to' => $status]);
        if ($actor instanceof AdminUser) {
            $this->outbox->customer($ticket, 'ticket-status', ['reference' => $ticket->public_reference, 'status' => $status], 'status:'.$status.':'.$ticket->resolution_cycle);
        }

        return $ticket->fresh();
    }

    private function calculatePriority(SupportCategory $category, ?string $requested): string
    {
        if (in_array($category->slug, ['account-login', 'subscription-payments', 'technical-bugs'], true) && $requested === 'high') {
            return 'high';
        }

        return $requested === 'low' ? 'low' : 'normal';
    }

    private function nextReference(): string
    {
        DB::table('support_sequences')->insertOrIgnore(['name' => 'ticket', 'value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('support_sequences')->where('name', 'ticket')->lockForUpdate()->first();
        $next = ((int) $row->value) + 1;
        DB::table('support_sequences')->where('name', 'ticket')->update(['value' => $next, 'updated_at' => now()]);

        return 'TX-SUP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    public function event(SupportTicket $ticket, string $type, string $actorType, ?int $actorId, array $payload = []): void
    {
        SupportTicketEvent::create(['ticket_id' => $ticket->id, 'event_type' => $type, 'actor_type' => $actorType, 'actor_id' => $actorId, 'payload' => $payload, 'created_at' => now()]);
    }
}
