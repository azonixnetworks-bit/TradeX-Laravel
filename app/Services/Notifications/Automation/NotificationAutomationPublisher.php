<?php

namespace App\Services\Notifications\Automation;

use App\Models\NotificationAutomationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationAutomationPublisher
{
    public function publish(
        string $eventKey,
        string $eventType,
        array $payload,
        ?string $sourceType = null,
        ?string $sourceReference = null
    ): ?NotificationAutomationEvent {
        if (! (bool) config('tradex_notification_automation.enabled', false)) {
            return null;
        }

        try {
            $event = NotificationAutomationEvent::query()->firstOrCreate(
                [
                    'event_key' => hash('sha256', $eventKey),
                ],
                [
                    'event_type' => strtoupper(trim($eventType)),
                    'source_type' => $sourceType !== null
                        ? strtoupper(trim($sourceType))
                        : null,
                    'source_reference' => $sourceReference,
                    'payload' => $payload,
                    'status' => NotificationAutomationEvent::STATUS_PENDING,
                    'attempts' => 0,
                    'next_attempt_at' => null,
                    'last_error_code' => null,
                    'notification_message_id' => null,
                    'recipient_count' => 0,
                    'processed_at' => null,
                ]
            );

            if (
                $event->wasRecentlyCreated
                && (bool) config(
                    'tradex_notification_automation.immediate_processing',
                    true
                )
            ) {
                $this->processAfterCommit((int) $event->getKey());
            }

            return $event;
        } catch (Throwable $exception) {
            Log::error('TradeX notification automation event could not be recorded.', [
                'event_type' => strtoupper(trim($eventType)),
                'source_type' => $sourceType,
                'source_reference' => $sourceReference,
                'error_class' => $exception::class,
            ]);

            return null;
        }
    }

    private function processAfterCommit(int $eventId): void
    {
        $callback = function () use ($eventId): void {
            try {
                app(NotificationAutomationProcessor::class)
                    ->process($eventId);
            } catch (Throwable $exception) {
                Log::warning(
                    'TradeX notification automation immediate processing deferred to sweep.',
                    [
                        'event_id' => $eventId,
                        'error_class' => $exception::class,
                    ]
                );
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }
}
