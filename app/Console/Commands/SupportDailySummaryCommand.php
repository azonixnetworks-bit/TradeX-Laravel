<?php

namespace App\Console\Commands;

use App\Jobs\DeliverSupportNotification;
use App\Models\AdminUser;
use App\Models\SupportNotificationDelivery;
use App\Models\SupportTicket;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SupportDailySummaryCommand extends Command
{
    protected $signature = 'tradex:support:daily-summary';

    protected $description = 'Queue an idempotent daily support operations summary for active super administrators.';

    public function handle(): int
    {
        $date = now(config('support.timezone'))->toDateString();
        $payload = [
            'date' => $date,
            'active' => SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting_for_customer'])->count(),
            'unassigned' => SupportTicket::query()->whereNull('assigned_admin_id')->whereIn('status', ['open', 'in_progress'])->count(),
            'overdue' => SupportTicket::query()->whereNull('first_responded_at')->where('first_response_due_at', '<', now())->whereIn('status', ['open', 'in_progress'])->count(),
        ];

        AdminUser::query()->where('is_active', true)->whereHas('roles', fn ($roles) => $roles->where('code', 'SUPER_ADMIN')->where('is_active', true))->each(function (AdminUser $admin) use ($date, $payload): void {
            $delivery = SupportNotificationDelivery::firstOrCreate(
                ['deduplication_key' => "support:daily:{$date}:admin:{$admin->id}:mail"],
                ['uuid' => (string) Str::uuid(), 'admin_user_id' => $admin->id, 'channel' => 'mail', 'template' => 'daily-summary', 'payload' => $payload, 'status' => 'pending']
            );
            if ($delivery->wasRecentlyCreated) {
                DeliverSupportNotification::dispatch($delivery->id)->afterCommit();
            }
        });

        $this->info('Support daily summary queued idempotently.');

        return self::SUCCESS;
    }
}
