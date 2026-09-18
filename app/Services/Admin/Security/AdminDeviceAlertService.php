<?php

namespace App\Services\Admin\Security;

use App\Jobs\SendAdminDeviceAlert;
use App\Models\AdminDeviceAlert;
use App\Models\AdminDeviceIncident;
use App\Models\AdminUser;
use Illuminate\Support\Facades\Log;

class AdminDeviceAlertService
{
    public function schedule(AdminDeviceIncident $incident): void
    {
        $emails = AdminUser::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('code', 'SUPER_ADMIN')->where('is_active', true))->pluck('email')->unique();
        $recipients = [];
        foreach ($emails as $email) {
            $recipients[] = ['channel' => 'email', 'recipient' => $email];
        }
        foreach (array_unique(config('tradex_admin_devices.whatsapp_recipients', [])) as $number) {
            $recipients[] = ['channel' => 'whatsapp', 'recipient' => $number];
        }
        // Expose missing configuration on the security screen instead of silently skipping WhatsApp.
        if (! config('tradex_admin_devices.whatsapp_recipients')) {
            AdminDeviceAlert::firstOrCreate(['admin_device_incident_id' => $incident->id, 'channel' => 'whatsapp', 'recipient' => 'NOT_CONFIGURED'], ['status' => 'FAILED', 'failure_code' => 'RECIPIENT_NOT_CONFIGURED']);
        }
        foreach ($recipients as $recipient) {
            $alert = AdminDeviceAlert::firstOrCreate(['admin_device_incident_id' => $incident->id, ...$recipient]);
            if (! $alert->wasRecentlyCreated) {
                continue;
            }
            try {
                SendAdminDeviceAlert::dispatch($alert->id)->afterCommit();
            } catch (\Throwable) {
                $alert->update(['status' => 'FAILED', 'failure_code' => 'DISPATCH_OR_DELIVERY_FAILED']);
                Log::warning('Admin device alert dispatch failed.', ['alert_id' => $alert->id]);
            }
        }
    }
}
