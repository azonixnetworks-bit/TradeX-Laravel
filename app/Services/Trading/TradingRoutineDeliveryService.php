<?php

namespace App\Services\Trading;

use App\Contracts\Trading\TradingRoutineWhatsAppGateway;
use App\Exceptions\TradingRoutineWhatsAppException;
use App\Models\AuditLog;
use App\Models\TradingRoutineDailyPlan;
use App\Models\TradingRoutineDelivery;
use App\Models\TradingRoutinePreference;

class TradingRoutineDeliveryService
{
    public function __construct(
        private readonly TradingRoutinePdfService $pdf,
        private readonly TradingRoutineWhatsAppGateway $whatsapp
    ) {
    }

    public function deliverWhatsApp(
        TradingRoutineDailyPlan $plan,
        TradingRoutinePreference $preference
    ): TradingRoutineDelivery {
        $delivery = TradingRoutineDelivery::query()->firstOrCreate(
            [
                'trading_routine_daily_plan_id' => $plan->id,
                'channel' => TradingRoutineDelivery::CHANNEL_WHATSAPP,
            ],
            [
                'destination_snapshot' => $plan->whatsapp_number_snapshot,
                'status' => TradingRoutineDelivery::STATUS_PENDING,
                'attempts' => 0,
            ]
        );

        if (in_array($delivery->status, [
            TradingRoutineDelivery::STATUS_ACCEPTED,
            TradingRoutineDelivery::STATUS_SKIPPED,
        ], true)) {
            return $delivery;
        }

        if (! $preference->whatsapp_pdf_enabled) {
            return $this->skip($delivery, 'WHATSAPP_OPT_IN_DISABLED', 'Customer WhatsApp PDF delivery is disabled.');
        }

        if (blank($plan->whatsapp_number_snapshot)) {
            return $this->skip($delivery, 'WHATSAPP_NOT_VERIFIED', 'A verified WhatsApp number is required for daily PDF delivery.');
        }

        $maxAttempts = max(1, (int) config('trading_routine.scheduler.max_delivery_attempts', 3));
        if ($delivery->attempts >= $maxAttempts) {
            return $delivery;
        }

        $retryAfter = max(1, (int) config('trading_routine.scheduler.retry_after_minutes', 15));
        if (
            $delivery->status === TradingRoutineDelivery::STATUS_FAILED
            && $delivery->last_attempt_at?->copy()->addMinutes($retryAfter)->isFuture()
        ) {
            return $delivery;
        }

        $delivery->forceFill([
            'status' => TradingRoutineDelivery::STATUS_PENDING,
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        try {
            $filename = 'tradex-daily-trading-plan-'.$plan->plan_date?->format('Y-m-d').'.pdf';
            $result = $this->whatsapp->sendPdf(
                (string) $plan->whatsapp_number_snapshot,
                $this->pdf->render($plan),
                $filename
            );

            $delivery->forceFill([
                'status' => TradingRoutineDelivery::STATUS_ACCEPTED,
                'provider' => $result['provider'],
                'provider_message_id' => $result['message_id'],
                'provider_media_id' => $result['media_id'],
                'accepted_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ])->save();

            AuditLog::record(
                'SYSTEM',
                null,
                'TRADING_ROUTINE_WHATSAPP_ACCEPTED',
                'TRADING_TOOLS',
                TradingRoutineDailyPlan::class,
                $plan->id,
                null,
                [
                    'delivery_id' => $delivery->id,
                    'provider' => $delivery->provider,
                    'attempt' => $delivery->attempts,
                ]
            );
        } catch (TradingRoutineWhatsAppException $exception) {
            $delivery->forceFill([
                'status' => TradingRoutineDelivery::STATUS_FAILED,
                'failure_code' => $exception->failureCode,
                'failure_message' => $exception->getMessage(),
            ])->save();

            AuditLog::record(
                'SYSTEM',
                null,
                'TRADING_ROUTINE_WHATSAPP_FAILED',
                'TRADING_TOOLS',
                TradingRoutineDailyPlan::class,
                $plan->id,
                null,
                [
                    'delivery_id' => $delivery->id,
                    'failure_code' => $exception->failureCode,
                    'attempt' => $delivery->attempts,
                ]
            );
        }

        return $delivery->fresh();
    }

    private function skip(
        TradingRoutineDelivery $delivery,
        string $code,
        string $message
    ): TradingRoutineDelivery {
        $delivery->forceFill([
            'status' => TradingRoutineDelivery::STATUS_SKIPPED,
            'failure_code' => $code,
            'failure_message' => $message,
        ])->save();

        return $delivery->fresh();
    }
}
