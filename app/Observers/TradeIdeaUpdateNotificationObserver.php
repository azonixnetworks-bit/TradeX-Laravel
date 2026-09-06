<?php

namespace App\Observers;

use App\Models\NotificationMessage;
use App\Models\TradeIdeaUpdate;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;
use Illuminate\Support\Str;

class TradeIdeaUpdateNotificationObserver
{
    public function created(TradeIdeaUpdate $update): void
    {
        if (! (bool) config(
            'tradex_notification_automation.events.trade_ideas',
            true
        )) {
            return;
        }

        $tradeIdea = $update->tradeIdea()->first();

        if ($tradeIdea === null) {
            return;
        }

        $label = match ($update->type) {
            TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED => 'Entry Triggered',
            TradeIdeaUpdate::TYPE_STOP_MOVED => 'Stop Loss Updated',
            TradeIdeaUpdate::TYPE_TARGET_HIT => 'Target Hit',
            TradeIdeaUpdate::TYPE_CLOSED => 'Trade Idea Closed',
            default => 'Trade Idea Update',
        };

        $priority = in_array($update->type, [
            TradeIdeaUpdate::TYPE_ENTRY_TRIGGERED,
            TradeIdeaUpdate::TYPE_STOP_MOVED,
            TradeIdeaUpdate::TYPE_TARGET_HIT,
            TradeIdeaUpdate::TYPE_CLOSED,
        ], true)
            ? NotificationMessage::PRIORITY_HIGH
            : NotificationMessage::PRIORITY_NORMAL;

        $detail = trim((string) $update->message);

        if ($detail === '' && $update->price !== null) {
            $detail = 'Price: '.$update->price;
        }

        $body = trim(sprintf(
            '%s · %s%s',
            strtoupper((string) $tradeIdea->symbol),
            $label,
            $detail !== '' ? ' · '.$detail : ''
        ));

        app(NotificationAutomationPublisher::class)->publish(
            eventKey: sprintf(
                'trade-idea:update:%s:%d',
                $tradeIdea->uuid,
                $update->getKey()
            ),
            eventType: 'TRADE_IDEA_UPDATE_ADDED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_PREMIUM,
                'category' => NotificationMessage::CATEGORY_TRADE_IDEA,
                'priority' => $priority,
                'title' => $label,
                'body' => Str::limit($body, 500),
                'action_type' => NotificationMessage::ACTION_TRADE_IDEA,
                'action_resource_uuid' => $tradeIdea->uuid,
            ],
            sourceType: 'TRADE_IDEA_UPDATE',
            sourceReference: (string) $update->getKey()
        );
    }
}
