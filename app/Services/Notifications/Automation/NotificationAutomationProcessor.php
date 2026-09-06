<?php

namespace App\Services\Notifications\Automation;

use App\Models\NotificationAutomationEvent;
use App\Models\NotificationMessage;
use App\Models\User;
use App\Services\Push\PushFanoutService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class NotificationAutomationProcessor
{
    private const RESOURCE_ACTION_TABLES = [
        NotificationMessage::ACTION_EDUCATION_COURSE => 'courses',
        NotificationMessage::ACTION_EDUCATION_LESSON => 'lessons',
        NotificationMessage::ACTION_TRADE_IDEA => 'trade_ideas',
        NotificationMessage::ACTION_OUR_CHANNEL_VIDEO => 'channel_videos',
    ];

    public function sweep(?int $requestedLimit = null): array
    {
        if (! (bool) config('tradex_notification_automation.enabled', false)) {
            return [
                'enabled' => false,
                'processed' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $limit = min(
            1000,
            max(
                1,
                $requestedLimit
                    ?? (int) config(
                        'tradex_notification_automation.sweep.batch_size',
                        100
                    )
            )
        );

        $maxAttempts = $this->maxAttempts();
        $now = now();

        $ids = NotificationAutomationEvent::query()
            ->whereIn('status', [
                NotificationAutomationEvent::STATUS_PENDING,
                NotificationAutomationEvent::STATUS_FAILED,
            ])
            ->where('attempts', '<', $maxAttempts)
            ->where(function (Builder $query) use ($now): void {
                $query
                    ->whereNull('next_attempt_at')
                    ->orWhere('next_attempt_at', '<=', $now);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $summary = [
            'enabled' => true,
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($ids as $id) {
            $result = $this->process((int) $id);

            if ($result['processed']) {
                $summary['processed']++;
            } elseif ($result['failed']) {
                $summary['failed']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    public function process(int $eventId): array
    {
        if (! (bool) config('tradex_notification_automation.enabled', false)) {
            return [
                'processed' => false,
                'failed' => false,
                'skipped' => true,
                'recipient_count' => 0,
            ];
        }

        try {
            $result = DB::transaction(
                function () use ($eventId): array {
                    $event = NotificationAutomationEvent::query()
                        ->lockForUpdate()
                        ->find($eventId);

                    if (! ($event instanceof NotificationAutomationEvent)) {
                        return [
                            'processed' => false,
                            'failed' => false,
                            'skipped' => true,
                            'recipient_count' => 0,
                        ];
                    }

                    if ($event->isProcessed()) {
                        return [
                            'processed' => false,
                            'failed' => false,
                            'skipped' => true,
                            'recipient_count' => $event->recipient_count,
                        ];
                    }

                    if ($event->attempts >= $this->maxAttempts()) {
                        return [
                            'processed' => false,
                            'failed' => false,
                            'skipped' => true,
                            'recipient_count' => $event->recipient_count,
                        ];
                    }

                    if (
                        $event->next_attempt_at !== null
                        && $event->next_attempt_at->isFuture()
                    ) {
                        return [
                            'processed' => false,
                            'failed' => false,
                            'skipped' => true,
                            'recipient_count' => $event->recipient_count,
                        ];
                    }

                    $payload = $this->validatedPayload($event->payload);
                    $recipientQuery = $this->recipientQuery($payload);
                    $recipientCount = (clone $recipientQuery)->count();

                    if ($recipientCount > $this->maxRecipients()) {
                        throw new RuntimeException(
                            'Notification automation recipient safety limit exceeded.'
                        );
                    }

                    $message = null;

                    if ($recipientCount > 0) {
                        $message = NotificationMessage::query()->create([
                            'audience_access_level' => $payload['audience'],
                            'category' => $payload['category'],
                            'priority' => $payload['priority'],
                            'title' => $payload['title'],
                            'body' => $payload['body'],
                            'action_type' => $payload['action_type'],
                            'action_resource_uuid' =>
                                $payload['action_resource_uuid'],
                            'is_active' => true,
                            'published_at' => now(),
                            'expires_at' => $payload['expires_at'],
                            'created_by_admin_id' => null,
                        ]);

                        $this->insertReceipts(
                            $message,
                            $recipientQuery,
                            $recipientCount
                        );
                    }

                    $event->forceFill([
                        'status' => NotificationAutomationEvent::STATUS_PROCESSED,
                        'attempts' => $event->attempts + 1,
                        'next_attempt_at' => null,
                        'last_error_code' => null,
                        'notification_message_id' => $message?->getKey(),
                        'recipient_count' => $recipientCount,
                        'processed_at' => now(),
                    ])->save();

                    return [
                        'processed' => true,
                        'failed' => false,
                        'skipped' => false,
                        'recipient_count' => $recipientCount,
                    ];
                },
                3
            );

            if (
                $result['processed']
                && $result['recipient_count'] > 0
                && (bool) config(
                    'tradex_notification_automation.immediate_push_fanout',
                    true
                )
            ) {
                app(PushFanoutService::class)->sweep();
            }

            return $result;
        } catch (Throwable $exception) {
            $this->recordFailure($eventId, $exception);

            return [
                'processed' => false,
                'failed' => true,
                'skipped' => false,
                'recipient_count' => 0,
            ];
        }
    }

    private function validatedPayload(array $payload): array
    {
        $audience = strtoupper(trim((string) ($payload['audience'] ?? '')));
        $category = strtoupper(trim((string) ($payload['category'] ?? '')));
        $priority = strtoupper(trim((string) ($payload['priority'] ?? '')));
        $actionType = strtoupper(trim((string) ($payload['action_type'] ?? '')));
        $title = trim((string) ($payload['title'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));

        if (! in_array($audience, [
            NotificationMessage::AUDIENCE_ALL,
            NotificationMessage::AUDIENCE_FREE,
            NotificationMessage::AUDIENCE_PREMIUM,
        ], true)) {
            throw new RuntimeException('Invalid automated notification audience.');
        }

        if (! in_array($category, NotificationMessage::CATEGORIES, true)) {
            throw new RuntimeException('Invalid automated notification category.');
        }

        if (! in_array($priority, NotificationMessage::PRIORITIES, true)) {
            throw new RuntimeException('Invalid automated notification priority.');
        }

        if (! in_array($actionType, NotificationMessage::ACTION_TYPES, true)) {
            throw new RuntimeException('Invalid automated notification action type.');
        }

        if (mb_strlen($title) < 3 || mb_strlen($title) > 180) {
            throw new RuntimeException('Invalid automated notification title.');
        }

        if (mb_strlen($body) < 3 || mb_strlen($body) > 5000) {
            throw new RuntimeException('Invalid automated notification body.');
        }

        $resourceUuid = filled($payload['action_resource_uuid'] ?? null)
            ? trim((string) $payload['action_resource_uuid'])
            : null;

        $this->assertActionContract($actionType, $resourceUuid);

        $expiresAt = filled($payload['expires_at'] ?? null)
            ? CarbonImmutable::parse((string) $payload['expires_at'])
            : null;

        if ($expiresAt !== null && ! $expiresAt->isFuture()) {
            throw new RuntimeException('Automated notification expiry must be in the future.');
        }

        $recipientIds = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $id): int => (int) $id,
                is_array($payload['recipient_user_ids'] ?? null)
                    ? $payload['recipient_user_ids']
                    : []
            ),
            static fn (int $id): bool => $id > 0
        )));

        return [
            'audience' => $audience,
            'category' => $category,
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'action_type' => $actionType,
            'action_resource_uuid' => $resourceUuid,
            'recipient_user_ids' => $recipientIds,
            'expires_at' => $expiresAt,
        ];
    }

    private function assertActionContract(
        string $actionType,
        ?string $resourceUuid
    ): void {
        $table = self::RESOURCE_ACTION_TABLES[$actionType] ?? null;

        if ($table === null) {
            if ($resourceUuid !== null) {
                throw new RuntimeException(
                    'This automated notification action does not accept a resource UUID.'
                );
            }

            return;
        }

        if ($resourceUuid === null) {
            throw new RuntimeException(
                'This automated notification action requires a resource UUID.'
            );
        }

        if (! preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $resourceUuid
        )) {
            throw new RuntimeException(
                'Invalid automated notification resource UUID.'
            );
        }

        if (! DB::table($table)->where('uuid', $resourceUuid)->exists()) {
            throw new RuntimeException(
                'The automated notification action resource does not exist.'
            );
        }
    }

    private function recipientQuery(array $payload): Builder
    {
        $query = User::query()->where('status', 'ACTIVE');

        if ($payload['recipient_user_ids'] !== []) {
            return $query->whereIn('id', $payload['recipient_user_ids']);
        }

        if ($payload['audience'] === NotificationMessage::AUDIENCE_FREE) {
            $query->where('account_type', 'FREE');
        } elseif (
            $payload['audience'] === NotificationMessage::AUDIENCE_PREMIUM
        ) {
            $query->where('account_type', 'PREMIUM');
        }

        return $query;
    }

    private function insertReceipts(
        NotificationMessage $message,
        Builder $recipientQuery,
        int $expectedCount
    ): void {
        $inserted = 0;
        $chunkSize = min(
            5000,
            max(
                50,
                (int) config(
                    'tradex_notification_automation.sweep.recipient_chunk_size',
                    1000
                )
            )
        );

        $recipientQuery
            ->select('id')
            ->orderBy('id')
            ->chunkById(
                $chunkSize,
                function ($users) use ($message, &$inserted): void {
                    $now = now();
                    $rows = [];

                    foreach ($users as $user) {
                        $rows[] = [
                            'uuid' => (string) Str::uuid(),
                            'notification_message_id' => $message->getKey(),
                            'user_id' => $user->getKey(),
                            'read_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        DB::table('user_notification_receipts')->insert($rows);
                        $inserted += count($rows);
                    }
                },
                'id'
            );

        if ($inserted !== $expectedCount) {
            throw new RuntimeException(
                'Notification automation recipient snapshot changed during dispatch.'
            );
        }
    }

    private function recordFailure(int $eventId, Throwable $exception): void
    {
        $event = NotificationAutomationEvent::query()->find($eventId);

        if (
            ! ($event instanceof NotificationAutomationEvent)
            || $event->isProcessed()
        ) {
            return;
        }

        $attempts = $event->attempts + 1;
        $maxAttempts = $this->maxAttempts();
        $retrySeconds = $this->retrySeconds($attempts);

        $event->forceFill([
            'status' => NotificationAutomationEvent::STATUS_FAILED,
            'attempts' => $attempts,
            'next_attempt_at' => $attempts < $maxAttempts
                ? now()->addSeconds($retrySeconds)
                : null,
            'last_error_code' => class_basename($exception),
        ])->save();

        Log::error('TradeX notification automation event processing failed.', [
            'event_id' => $eventId,
            'event_type' => $event->event_type,
            'attempts' => $attempts,
            'error_class' => $exception::class,
        ]);
    }

    private function maxAttempts(): int
    {
        return min(
            20,
            max(
                1,
                (int) config(
                    'tradex_notification_automation.sweep.max_attempts',
                    5
                )
            )
        );
    }

    private function maxRecipients(): int
    {
        return min(
            1000000,
            max(
                1,
                (int) config(
                    'tradex_notification_automation.sweep.max_recipients',
                    250000
                )
            )
        );
    }

    private function retrySeconds(int $attempt): int
    {
        $schedule = config(
            'tradex_notification_automation.sweep.retry_seconds',
            [60, 300, 900, 3600, 21600]
        );

        if (! is_array($schedule) || $schedule === []) {
            return 60;
        }

        $index = min(count($schedule) - 1, max(0, $attempt - 1));

        return max(30, (int) $schedule[$index]);
    }
}
