<?php

namespace App\Services\Payouts;

use App\Models\NotificationMessage;
use App\Models\PayoutRequest;
use App\Models\UserNotificationReceipt;
use Illuminate\Support\Facades\Cache;

class PayoutStatusNotificationService
{
    public function send(PayoutRequest $payout, string $stage): void
    {
        $content = match ($stage) {
            'REQUESTED' => [
                'Withdrawal requested',
                'Your withdrawal request was received. Processing normally takes 3 to 5 business days.',
            ],
            'PROCESSING' => [
                'Withdrawal processing',
                'Your withdrawal request is being reviewed and processed.',
            ],
            'ON_QUEUE' => [
                'Withdrawal on queue',
                'Your withdrawal is in the payment queue and awaiting settlement.',
            ],
            'COMPLETED' => [
                'Withdrawal completed',
                'Your withdrawal has been completed successfully.',
            ],
            'REJECTED' => [
                'Withdrawal update',
                'Your withdrawal request was not approved. Open payout history for details.',
            ],
            default => null,
        };

        if ($content === null) {
            return;
        }

        $message = NotificationMessage::query()->firstOrCreate(
            [
                'action_resource_uuid' => $payout->uuid,
                'title' => $content[0],
            ],
            [
                'audience_access_level' => NotificationMessage::AUDIENCE_ALL,
                'category' => NotificationMessage::CATEGORY_PAYMENT,
                'priority' => NotificationMessage::PRIORITY_NORMAL,
                'body' => $content[1],
                'action_type' => NotificationMessage::ACTION_AFFILIATE,
                'is_active' => true,
                'published_at' => now(),
                'expires_at' => now()->addDays(45),
            ]
        );

        UserNotificationReceipt::query()->firstOrCreate([
            'notification_message_id' => $message->getKey(),
            'user_id' => $payout->user_id,
        ]);

        Cache::forget('tradex:notifications:summary:v1:'.$payout->user_id.':FREE');
        Cache::forget('tradex:notifications:summary:v1:'.$payout->user_id.':PREMIUM');
    }
}
