<?php

namespace App\Jobs;

use App\Models\PushNotificationDelivery;
use App\Services\Push\PushDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class SendPushDeliveryJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;

    public function __construct(public readonly int $deliveryId)
    {
    }

    public function backoff(): array
    {
        return array_values(array_map(
            static fn (mixed $seconds): int => max(1, (int) $seconds),
            (array) config(
                'tradex_push.retry_backoff_seconds',
                [60, 300, 900, 3600]
            )
        ));
    }

    public function handle(PushDeliveryService $service): void
    {
        $outcome = $service->deliver(
            $this->deliveryId,
            max(1, $this->attempts())
        );

        if (($outcome['retry'] ?? false) === true) {
            throw new RuntimeException(
                'Transient push provider failure; queue retry requested.'
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = PushNotificationDelivery::query()
            ->find($this->deliveryId);

        if (
            ! $delivery instanceof PushNotificationDelivery
            || $delivery->isTerminal()
        ) {
            return;
        }

        $delivery->forceFill([
            'status' => PushNotificationDelivery::STATUS_FAILED,
            'last_error_code' =>
                $delivery->last_error_code ?: 'QUEUE_JOB_FAILED',
            'failed_at' => now(),
            'next_attempt_at' => null,
        ])->save();
    }
}
