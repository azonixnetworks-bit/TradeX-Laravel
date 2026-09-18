<?php

namespace App\Jobs;

use App\Models\AdminDeviceAlert;
use App\Models\AdminDeviceIncident;
use App\Models\AdminUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SendAdminDeviceAlert implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public int $alertId) {}

    public function handle(): void
    {
        $alert = AdminDeviceAlert::find($this->alertId);
        if (! $alert || $alert->status === 'ACCEPTED') {
            return;
        }
        $incident = AdminDeviceIncident::findOrFail($alert->admin_device_incident_id);
        $admin = AdminUser::withTrashed()->findOrFail($incident->admin_user_id);
        $details = "Admin: {$admin->name} ({$admin->email}); Device ID: {$incident->admin_device_id}; Category: {$incident->device_slot}; IP: {$incident->ip_address}; UTC: {$incident->created_at->utc()->toIso8601String()}; Reason: {$incident->reason}; Browser/OS (reported): {$incident->user_agent}; IMEI: unavailable in browser. New-device access denied; approved devices remain active.";
        $alert->increment('attempts');
        try {
            if ($alert->channel === 'email') {
                // Re-check recipient authority at delivery time (a queued recipient may have lost their role).
                $recipient = AdminUser::where('email', $alert->recipient)->where('is_active', true)->first();
                if (! $recipient?->isSuperAdmin()) {
                    $alert->update(['status' => 'SKIPPED', 'failure_code' => 'RECIPIENT_NO_LONGER_SUPER_ADMIN']);

                    return;
                }
                Mail::raw("TradeX blocked an admin device login after credentials were verified.\n\n".$details."\n\nReview: ".route('admin.administration.admins.devices.index', $admin->id), function ($message) use ($alert) {
                    $message->to($alert->recipient)->subject('TradeX security: admin device login blocked');
                });
            } else {
                $this->sendWhatsApp($alert->recipient, $details);
            }
            $alert->update(['status' => 'ACCEPTED', 'accepted_at' => now(), 'failure_code' => null]);
        } catch (\Throwable) {
            $alert->update(['status' => 'FAILED', 'failure_code' => 'PROVIDER_OR_CONFIGURATION_ERROR']);
            // Do not persist provider exception text: it can contain URLs, tokens or private response bodies.
            throw new \RuntimeException('Admin device alert delivery failed; inspect configuration and provider status.');
        }
    }

    private function sendWhatsApp(string $recipient, string $details): void
    {
        $meta = config('tradex_registration.delivery.meta');
        $template = (string) config('tradex_admin_devices.whatsapp_template');
        $language = (string) config('tradex_admin_devices.whatsapp_language');
        if (! in_array($recipient, config('tradex_admin_devices.whatsapp_recipients', []), true)
            || ! preg_match('/^\+?[1-9][0-9]{6,14}$/D', $recipient)
            || ! preg_match('/^v\d+\.\d+$/D', (string) ($meta['graph_version'] ?? ''))
            || ! preg_match('/^\d+$/D', (string) ($meta['phone_number_id'] ?? ''))
            || empty($meta['access_token'])
            || ! preg_match('/^[a-z0-9_]{1,512}$/D', $template)
            || ! preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $language)) {
            throw new \RuntimeException('WhatsApp security alert configuration is incomplete.');
        }
        $response = Http::withToken($meta['access_token'])->acceptJson()->asJson()->connectTimeout(5)->timeout(15)
            ->post('https://graph.facebook.com/'.$meta['graph_version'].'/'.$meta['phone_number_id'].'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => ltrim($recipient, '+'),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $language],
                    'components' => [['type' => 'body', 'parameters' => [['type' => 'text', 'text' => mb_substr(preg_replace('/\s+/', ' ', $details), 0, 1000)]]]],
                ],
            ]);
        if (! $response->successful() || ! $response->json('messages.0.id')) {
            throw new \RuntimeException('WhatsApp security alert was not accepted.');
        }
    }
}
