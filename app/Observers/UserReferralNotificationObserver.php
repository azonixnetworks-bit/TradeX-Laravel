<?php

namespace App\Observers;

use App\Models\NotificationMessage;
use App\Models\User;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;

class UserReferralNotificationObserver
{
    public function created(User $user): void
    {
        if (
            ! (bool) config(
                'tradex_notification_automation.events.referrals',
                true
            )
            || $user->referred_by_user_id === null
        ) {
            return;
        }

        app(NotificationAutomationPublisher::class)->publish(
            eventKey: 'affiliate:referral-joined:'.$user->uuid,
            eventType: 'AFFILIATE_REFERRAL_JOINED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_ALL,
                'recipient_user_ids' => [
                    (int) $user->referred_by_user_id,
                ],
                'category' => NotificationMessage::CATEGORY_AFFILIATE,
                'priority' => NotificationMessage::PRIORITY_NORMAL,
                'title' => 'New Referral Joined',
                'body' => 'A new user joined TradeX using your referral link.',
                'action_type' => NotificationMessage::ACTION_AFFILIATE,
                'action_resource_uuid' => null,
            ],
            sourceType: 'USER',
            sourceReference: $user->uuid
        );
    }
}
