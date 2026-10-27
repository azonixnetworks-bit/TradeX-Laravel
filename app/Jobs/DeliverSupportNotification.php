<?php

namespace App\Jobs;

use App\Mail\SupportTicketMail;
use App\Models\AdminUser;
use App\Models\NotificationMessage;
use App\Models\SupportNotificationDelivery;
use App\Models\User;
use App\Models\UserNotificationReceipt;
use App\Services\Push\PushFanoutService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DeliverSupportNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $deliveryId)
    {
        $this->onQueue((string) config('support.notification_queue'));
    }

    public function handle(): void
    {
        $delivery = SupportNotificationDelivery::query()->findOrFail($this->deliveryId);
        if (in_array($delivery->status, ['sent', 'skipped'], true)) {
            return;
        }

        if ($delivery->channel === 'in_app') {
            $this->deliverInApp($delivery);

            return;
        }

        if (! config('support.mail_enabled')) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'Support mail transport is disabled.']);

            return;
        }

        $recipient = $delivery->user_id
            ? User::query()->find($delivery->user_id)
            : AdminUser::query()->find($delivery->admin_user_id);
        if (! $recipient?->email_verified_at) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'Recipient has no verified email.']);

            return;
        }

        $delivery->increment('attempts');
        Mail::to($recipient->email)->send(new SupportTicketMail($delivery->template, $delivery->payload));
        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(Throwable $exception): void
    {
        SupportNotificationDelivery::query()->whereKey($this->deliveryId)->update([
            'status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
    }

    private function deliverInApp(SupportNotificationDelivery $delivery): void
    {
        if (! $delivery->user_id) {
            $delivery->update(['status' => 'skipped', 'last_error' => 'In-app support delivery requires a customer.']);

            return;
        }

        $title = match ($delivery->template) {
            'ticket-received' => 'Support ticket received',
            'admin-reply' => 'Support replied',
            'ticket-assigned' => 'Support ticket assigned',
            'ticket-status' => 'Support status updated',
            default => 'Support update',
        };
        $body = match ($delivery->template) {
            'ticket-received' => 'Your support request has been recorded securely.',
            'admin-reply' => 'You have a new reply in the TradeX support centre.',
            'ticket-assigned' => 'A support agent has accepted your request.',
            'ticket-status' => 'Your support request status has changed.',
            default => 'Open the TradeX support centre for details.',
        };

        $message = NotificationMessage::query()->firstOrCreate(
            ['uuid' => $delivery->uuid],
            [
                'audience_access_level' => NotificationMessage::AUDIENCE_ALL,
                'category' => NotificationMessage::CATEGORY_SYSTEM,
                'priority' => NotificationMessage::PRIORITY_NORMAL,
                'title' => $title,
                // Generic notification storage is visible to notification admins.
                // Keep customer case text and references inside the support boundary.
                'body' => $body,
                'action_type' => NotificationMessage::ACTION_NONE,
                'is_active' => true,
                'published_at' => now(),
                'expires_at' => now()->addDays(30),
            ]
        );

        UserNotificationReceipt::query()->firstOrCreate([
            'notification_message_id' => $message->id,
            'user_id' => $delivery->user_id,
        ]);

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        app(PushFanoutService::class)->sweep();
    }
}
