<?php

namespace App\Console\Commands;

use App\Models\AdminUser;
use App\Models\SupportAgentProfile;
use App\Models\SupportChatSession;
use App\Models\SupportTicket;
use App\Models\SupportTicketEvent;
use App\Services\Support\SupportNotificationOutbox;
use App\Services\Support\SupportTicketService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupportSlaSweepCommand extends Command
{
    protected $signature = 'tradex:support:sweep';

    protected $description = 'Expire stale support presence, requeue disconnected chats and record SLA escalation events.';

    public function handle(SupportTicketService $service, SupportNotificationOutbox $outbox): int
    {
        $cutoff = now()->subSeconds((int) config('support.agent_presence_ttl_seconds'));
        SupportAgentProfile::query()->whereIn('availability', ['online', 'away'])->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', $cutoff))->update(['availability' => 'offline']);

        SupportChatSession::query()->whereIn('state', ['assigned', 'active'])->where(fn ($q) => $q->whereNull('last_agent_heartbeat_at')->orWhere('last_agent_heartbeat_at', '<', $cutoff))->chunkById(100, function ($sessions) use ($service): void {
            foreach ($sessions as $session) {
                DB::transaction(function () use ($session, $service): void {
                    $locked = SupportChatSession::query()->whereKey($session->id)->lockForUpdate()->first();
                    if (! $locked || ! in_array($locked->state, ['assigned', 'active'], true)) {
                        return;
                    }
                    $locked->update(['state' => 'queued', 'accepted_by_admin_id' => null, 'accepted_at' => null, 'last_agent_heartbeat_at' => null, 'queued_at' => now()]);
                    $locked->ticket()->update(['assigned_admin_id' => null, 'status' => 'open']);
                    $service->event($locked->ticket, 'chat.requeued_agent_timeout', 'system', null, ['chat_uuid' => $locked->uuid]);
                });
            }
        });

        SupportTicket::query()->whereNull('first_responded_at')->where('first_response_due_at', '<=', now())->whereIn('status', ['open', 'in_progress'])->chunkById(100, function ($tickets) use ($service, $outbox): void {
            foreach ($tickets as $ticket) {
                if (SupportTicketEvent::query()->where('ticket_id', $ticket->id)->where('event_type', 'sla.first_response_breached')->exists()) {
                    continue;
                }
                $service->event($ticket, 'sla.first_response_breached', 'system', null, ['due_at' => $ticket->first_response_due_at?->toISOString()]);
                if (in_array($ticket->priority, ['low', 'normal'], true)) {
                    $ticket->update(['priority' => 'high']);
                }
                AdminUser::query()->where('is_active', true)->where(function ($query): void {
                    $query->whereHas('roles', fn ($roles) => $roles->where('code', 'SUPER_ADMIN'))->orWhereHas('roles.permissions', fn ($permissions) => $permissions->where('code', 'support.chat.supervise'));
                })->each(fn (AdminUser $admin) => $outbox->admin($ticket, $admin, 'sla-breach', ['reference' => $ticket->public_reference], 'sla-first-response-breach'));
            }
        });

        $this->info('Support SLA and presence sweep completed.');

        return self::SUCCESS;
    }
}
