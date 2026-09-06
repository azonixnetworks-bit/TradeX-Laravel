<?php

namespace App\Observers;

use App\Models\DailyTradeIdea;
use App\Models\NotificationMessage;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;
use Illuminate\Support\Str;

class DailyTradeIdeaNotificationObserver
{
    public function updated(DailyTradeIdea $idea): void
    {
        if (! (bool) config(
            'tradex_notification_automation.events.trade_ideas',
            true
        )) {
            return;
        }

        if ($idea->wasChanged('status') && $idea->isPublished()) {
            $this->published($idea);

            return;
        }

        if (
            $idea->isPublished()
            && $idea->wasChanged([
                'chart_image_path',
                'tradingview_url',
            ])
        ) {
            $this->chartUpdated($idea);
        }
    }

    private function published(DailyTradeIdea $idea): void
    {
        app(NotificationAutomationPublisher::class)->publish(
            eventKey: 'daily-trade-idea:published:'.$idea->uuid,
            eventType: 'DAILY_TRADE_IDEA_PUBLISHED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_PREMIUM,
                'category' => NotificationMessage::CATEGORY_TRADE_IDEA,
                'priority' => NotificationMessage::PRIORITY_HIGH,
                'title' => 'New Day Trade Idea',
                'body' => $this->summary(
                    $idea,
                    'A new day-trade setup is available.'
                ),
                'action_type' => NotificationMessage::ACTION_DASHBOARD,
                'action_resource_uuid' => null,
            ],
            sourceType: 'DAILY_TRADE_IDEA',
            sourceReference: $idea->uuid
        );
    }

    private function chartUpdated(DailyTradeIdea $idea): void
    {
        $version = hash(
            'sha256',
            implode('|', [
                (string) $idea->getOriginal('chart_image_path'),
                (string) $idea->chart_image_path,
                (string) $idea->getOriginal('tradingview_url'),
                (string) $idea->tradingview_url,
                (string) $idea->updated_at?->format('Y-m-d H:i:s.u'),
            ])
        );

        app(NotificationAutomationPublisher::class)->publish(
            eventKey: 'daily-trade-idea:chart:'.$idea->uuid.':'.$version,
            eventType: 'DAILY_TRADE_IDEA_CHART_UPDATED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_PREMIUM,
                'category' => NotificationMessage::CATEGORY_TRADE_IDEA,
                'priority' => NotificationMessage::PRIORITY_HIGH,
                'title' => 'Day Trade Chart Updated',
                'body' => $this->summary(
                    $idea,
                    'The day-trade chart has been updated.'
                ),
                'action_type' => NotificationMessage::ACTION_DASHBOARD,
                'action_resource_uuid' => null,
            ],
            sourceType: 'DAILY_TRADE_IDEA',
            sourceReference: $idea->uuid
        );
    }

    private function summary(DailyTradeIdea $idea, string $message): string
    {
        return Str::limit(
            trim(sprintf(
                '%s %s %s · %s',
                strtoupper((string) $idea->symbol),
                strtoupper((string) $idea->direction),
                strtoupper((string) $idea->timeframe),
                $message
            )),
            500
        );
    }
}
