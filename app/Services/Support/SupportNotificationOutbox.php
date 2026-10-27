<?php

namespace App\Services\Support;

use App\Jobs\DeliverSupportNotification;
use App\Models\AdminUser;
use App\Models\SupportNotificationDelivery;
use App\Models\SupportTicket;
use Illuminate\Support\Str;

class SupportNotificationOutbox
{
    public function customer(SupportTicket $ticket, string $template, array $payload, string $eventKey): SupportNotificationDelivery
    {
        $delivery = SupportNotificationDelivery::firstOrCreate(
            ['deduplication_key' => "support:{$ticket->id}:{$eventKey}:mail"],
            [
                'uuid' => (string) Str::uuid(),
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'channel' => 'mail',
                'template' => $template,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($delivery->wasRecentlyCreated) {
            DeliverSupportNotification::dispatch($delivery->id)->afterCommit();
        }

        $inApp = SupportNotificationDelivery::firstOrCreate(
            ['deduplication_key' => "support:{$ticket->id}:{$eventKey}:in_app"],
            [
                'uuid' => (string) Str::uuid(),
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
                'channel' => 'in_app',
                'template' => $template,
                'payload' => $payload,
                'status' => 'pending',
            ]
        );

        if ($inApp->wasRecentlyCreated) {
            DeliverSupportNotification::dispatch($inApp->id)->afterCommit();
        }

        return $delivery;
    }

    public function admin(SupportTicket $ticket, AdminUser $admin, string $template, array $payload, string $eventKey): SupportNotificationDelivery
    {
        $delivery = SupportNotificationDelivery::firstOrCreate(
            ['deduplication_key' => "support:{$ticket->id}:{$eventKey}:admin:{$admin->id}:mail"],
            ['uuid' => (string) Str::uuid(), 'ticket_id' => $ticket->id, 'admin_user_id' => $admin->id, 'channel' => 'mail', 'template' => $template, 'payload' => $payload, 'status' => 'pending']
        );
        if ($delivery->wasRecentlyCreated) {
            DeliverSupportNotification::dispatch($delivery->id)->afterCommit();
        }

        return $delivery;
    }

    public function queueTeam(SupportTicket $ticket): void
    {
        AdminUser::query()->where('is_active', true)->where(function ($query): void {
            $query->whereHas('roles', fn ($roles) => $roles->where('code', 'SUPER_ADMIN')->where('is_active', true))
                ->orWhereHas('roles', fn ($roles) => $roles->where('is_active', true)->whereHas('permissions', fn ($permissions) => $permissions->where('code', 'support.tickets.view_all')));
        })->each(fn (AdminUser $admin) => $this->admin($ticket, $admin, 'new-ticket-queue', ['reference' => $ticket->public_reference, 'subject' => $ticket->subject], 'queue-created'));
    }
}
