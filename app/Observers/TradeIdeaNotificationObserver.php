<?php

namespace App\Observers;

use App\Models\NotificationMessage;
use App\Models\TradeIdea;
use App\Services\Notifications\Automation\NotificationAutomationPublisher;
use Illuminate\Support\Str;

class TradeIdeaNotificationObserver
{
    public function updated(TradeIdea $tradeIdea): void
    {
        if (! (bool) config(
            'tradex_notification_automation.events.trade_ideas',
            true
        )) {
            return;
        }

        if ($tradeIdea->wasChanged('status') && $tradeIdea->isPublished()) {
            $this->published($tradeIdea);

            return;
        }

        if (
            $tradeIdea->isPublished()
            && $tradeIdea->wasChanged([
                'chart_image_path',
                'tradingview_url',
            ])
        ) {
            $this->chartUpdated($tradeIdea);
        }
    }

    private function published(TradeIdea $tradeIdea): void
    {
        app(NotificationAutomationPublisher::class)->publish(
            eventKey: 'trade-idea:published:'.$tradeIdea->uuid,
            eventType: 'TRADE_IDEA_PUBLISHED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_PREMIUM,
                'category' => NotificationMessage::CATEGORY_TRADE_IDEA,
                'priority' => NotificationMessage::PRIORITY_HIGH,
                'title' => 'New Long-Term Trade Idea',
                'body' => $this->summary(
                    $tradeIdea,
                    'A new long-term Trade Idea is available.'
                ),
                'action_type' => NotificationMessage::ACTION_TRADE_IDEA,
                'action_resource_uuid' => $tradeIdea->uuid,
            ],
            sourceType: 'TRADE_IDEA',
            sourceReference: $tradeIdea->uuid
        );
    }

    private function chartUpdated(TradeIdea $tradeIdea): void
    {
        $version = hash(
            'sha256',
            implode('|', [
                (string) $tradeIdea->getOriginal('chart_image_path'),
                (string) $tradeIdea->chart_image_path,
                (string) $tradeIdea->getOriginal('tradingview_url'),
                (string) $tradeIdea->tradingview_url,
                (string) $tradeIdea->updated_at?->format('Y-m-d H:i:s.u'),
            ])
        );

        app(NotificationAutomationPublisher::class)->publish(
            eventKey: 'trade-idea:chart:'.$tradeIdea->uuid.':'.$version,
            eventType: 'TRADE_IDEA_CHART_UPDATED',
            payload: [
                'audience' => NotificationMessage::AUDIENCE_PREMIUM,
                'category' => NotificationMessage::CATEGORY_TRADE_IDEA,
                'priority' => NotificationMessage::PRIORITY_HIGH,
                'title' => 'Trade Idea Chart Updated',
                'body' => $this->summary(
                    $tradeIdea,
                    'The long-term Trade Idea chart has been updated.'
                ),
                'action_type' => NotificationMessage::ACTION_TRADE_IDEA,
                'action_resource_uuid' => $tradeIdea->uuid,
            ],
            sourceType: 'TRADE_IDEA',
            sourceReference: $tradeIdea->uuid
        );
    }

    private function summary(TradeIdea $tradeIdea, string $message): string
    {
        return Str::limit(
            trim(sprintf(
                '%s %s %s · %s',
                strtoupper((string) $tradeIdea->symbol),
                strtoupper((string) $tradeIdea->direction),
                strtoupper((string) $tradeIdea->timeframe),
                $message
            )),
            500
        );
    }
}
