<?php

namespace App\Services\Push;

use App\Jobs\SendPushDeliveryJob;
use App\Models\PushNotificationDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Throwable;

class PushFanoutService
{
    public function sweep(?int $requestedLimit = null): array
    {
        if (! (bool) config('tradex_push.enabled', false)) {
            return [
                'enabled' => false,
                'created' => 0,
                'queued' => 0,
            ];
        }

        $limit = min(
            5000,
            max(
                1,
                $requestedLimit
                    ?? (int) config('tradex_push.sweep.batch_size', 500)
            )
        );

        $now = now();

        $candidates = DB::table('user_notification_receipts as r')
            ->join(
                'notification_messages as n',
                'n.id',
                '=',
                'r.notification_message_id'
            )
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->join('push_device_tokens as p', 'p.user_id', '=', 'r.user_id')
            ->join('user_devices as d', 'd.id', '=', 'p.user_device_id')
            ->leftJoin(
                'push_notification_deliveries as pd',
                function ($join): void {
                    $join->on(
                        'pd.user_notification_receipt_id',
                        '=',
                        'r.id'
                    )->on(
                        'pd.push_device_token_id',
                        '=',
                        'p.id'
                    );
                }
            )
            ->whereNull('pd.id')
            ->where('n.is_active', true)
            ->whereNotNull('n.published_at')
            ->where('n.published_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('n.expires_at')
                    ->orWhere('n.expires_at', '>', $now);
            })
            ->where('u.status', 'ACTIVE')
            ->where(function ($query): void {
                $query
                    ->where('n.audience_access_level', 'ALL')
                    ->orWhere(function ($free): void {
                        $free->where('n.audience_access_level', 'FREE')
                            ->where('u.account_type', 'FREE');
                    })
                    ->orWhere(function ($premium): void {
                        $premium->where('n.audience_access_level', 'PREMIUM')
                            ->where('u.account_type', 'PREMIUM');
                    });
            })
            ->where('p.is_active', true)
            ->whereNull('p.revoked_at')
            ->whereNull('p.invalidated_at')
            ->whereColumn('p.registered_at', '<=', 'n.published_at')
            ->where('d.device_slot', 'MOBILE')
            ->where('d.is_active', true)
            ->where('d.is_trusted', true)
            ->whereNull('d.revoked_at')
            ->orderBy('r.id')
            ->orderBy('p.id')
            ->limit($limit)
            ->get([
                'r.id as receipt_id',
                'r.user_id as user_id',
                'p.id as token_id',
                'p.provider as provider',
            ]);

        $created = 0;
        $queued = 0;

        foreach ($candidates as $candidate) {
            $delivery = PushNotificationDelivery::query()->firstOrCreate(
                [
                    'user_notification_receipt_id' => $candidate->receipt_id,
                    'push_device_token_id' => $candidate->token_id,
                ],
                [
                    'user_id' => $candidate->user_id,
                    'provider' => $candidate->provider,
                    'status' => PushNotificationDelivery::STATUS_PENDING,
                    'attempts' => 0,
                    'next_attempt_at' => now(),
                ]
            );

            if ($delivery->wasRecentlyCreated) {
                $created++;
            }

            if ($this->queueDelivery($delivery)) {
                $queued++;
            }
        }

        $remaining = max(0, $limit - $queued);

        if ($remaining > 0) {
            $pending = PushNotificationDelivery::query()
                ->where('status', PushNotificationDelivery::STATUS_PENDING)
                ->where(function ($query): void {
                    $query->whereNull('next_attempt_at')
                        ->orWhere('next_attempt_at', '<=', now());
                })
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($pending as $delivery) {
                if ($this->queueDelivery($delivery)) {
                    $queued++;
                }
            }
        }

        return [
            'enabled' => true,
            'created' => $created,
            'queued' => $queued,
        ];
    }

    private function queueDelivery(
        PushNotificationDelivery $delivery
    ): bool {
        $claimed = DB::transaction(
            function () use ($delivery): ?PushNotificationDelivery {
                $locked = PushNotificationDelivery::query()
                    ->lockForUpdate()
                    ->find($delivery->getKey());

                if (
                    ! $locked instanceof PushNotificationDelivery
                    || $locked->status !== PushNotificationDelivery::STATUS_PENDING
                    || (
                        $locked->next_attempt_at !== null
                        && $locked->next_attempt_at->isFuture()
                    )
                ) {
                    return null;
                }

                $locked->forceFill([
                    'status' => PushNotificationDelivery::STATUS_QUEUED,
                    'queued_at' => now(),
                ])->save();

                return $locked;
            },
            3
        );

        if (! $claimed instanceof PushNotificationDelivery) {
            return false;
        }

        try {
            Queue::push(
                new SendPushDeliveryJob($claimed->getKey()),
                '',
                (string) config(
                    'tradex_push.queue',
                    'push-notifications'
                )
            );

            return true;
        } catch (Throwable) {
            $fresh = PushNotificationDelivery::query()
                ->find($claimed->getKey());

            if (
                $fresh instanceof PushNotificationDelivery
                && $fresh->status === PushNotificationDelivery::STATUS_QUEUED
            ) {
                $fresh->forceFill([
                    'status' => PushNotificationDelivery::STATUS_PENDING,
                    'last_error_code' => 'QUEUE_DISPATCH_FAILED',
                    'next_attempt_at' => now()->addMinute(),
                ])->save();
            }

            return false;
        }
    }
}
