<?php

namespace App\Services\Trading;

use App\Models\TradingRoutineDelivery;
use App\Models\TradingRoutinePreference;
use Carbon\CarbonImmutable;
use Throwable;

class TradingRoutineSweepService
{
    public function __construct(
        private readonly TradingRoutineDailyPlanService $plans,
        private readonly TradingRoutineDeliveryService $deliveries
    ) {
    }

    /** @return array{enabled:bool,processed:int,accepted:int,failed:int,skipped:int} */
    public function sweep(?int $batchSize = null, ?CarbonImmutable $now = null): array
    {
        if (! (bool) config('trading_routine.enabled', true)) {
            return [
                'enabled' => false,
                'processed' => 0,
                'accepted' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $batchSize = max(1, min(
            $batchSize ?: (int) config('trading_routine.scheduler.batch_limit', 250),
            1000
        ));
        $now = $now ?: CarbonImmutable::now('UTC');
        $result = [
            'enabled' => true,
            'processed' => 0,
            'accepted' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        TradingRoutinePreference::query()
            ->where('enabled', true)
            ->with('user')
            ->orderBy('id')
            ->chunkById(
                $batchSize,
                function ($preferences) use ($now, &$result): void {
                    foreach ($preferences as $preference) {
                        if (! $preference instanceof TradingRoutinePreference) {
                            continue;
                        }

                        $user = $preference->user;
                        if (! $user || ! $this->isDue($preference, $now)) {
                            continue;
                        }

                        try {
                            $plan = $this->plans->createOrGet($user, $preference, $now);
                            $result['processed']++;

                            if (! $preference->whatsapp_pdf_enabled) {
                                $result['skipped']++;
                                continue;
                            }

                            $delivery = $this->deliveries->deliverWhatsApp($plan, $preference);

                            match ($delivery->status) {
                                TradingRoutineDelivery::STATUS_ACCEPTED => $result['accepted']++,
                                TradingRoutineDelivery::STATUS_FAILED => $result['failed']++,
                                default => $result['skipped']++,
                            };
                        } catch (Throwable) {
                            $result['failed']++;
                        }
                    }
                },
                'id'
            );

        return $result;
    }

    public function isDue(
        TradingRoutinePreference $preference,
        CarbonImmutable $now
    ): bool {
        try {
            $localNow = $now->setTimezone($preference->timezone);
        } catch (Throwable) {
            return false;
        }

        $time = substr((string) $preference->delivery_time_local, 0, 5);
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
            return false;
        }

        $start = CarbonImmutable::createFromFormat(
            'Y-m-d H:i',
            $localNow->format('Y-m-d').' '.$time,
            $preference->timezone
        );

        if (! $start) {
            return false;
        }

        $window = max(1, (int) config('trading_routine.scheduler.delivery_window_minutes', 180));

        return $localNow->greaterThanOrEqualTo($start)
            && $localNow->lessThanOrEqualTo($start->addMinutes($window));
    }
}
