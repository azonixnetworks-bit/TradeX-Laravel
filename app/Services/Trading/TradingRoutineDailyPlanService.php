<?php

namespace App\Services\Trading;

use App\Models\TradingRoutineDailyPlan;
use App\Models\TradingRoutinePreference;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class TradingRoutineDailyPlanService
{
    public function __construct(
        private readonly TradingRoutinePlannerService $planner
    ) {
    }

    public function createOrGet(
        User $user,
        TradingRoutinePreference $preference,
        ?CarbonImmutable $now = null
    ): TradingRoutineDailyPlan {
        $timezone = $preference->timezone ?: (string) config('trading_routine.defaults.timezone', 'Asia/Colombo');
        $now = ($now ?: CarbonImmutable::now('UTC'))->setTimezone($timezone);
        $planDate = $now->toDateString();

        $existing = TradingRoutineDailyPlan::query()
            ->where('user_id', $user->id)
            ->whereDate('plan_date', $planDate)
            ->first();

        if ($existing) {
            return $existing;
        }

        $preferenceSnapshot = [
            'timezone' => $timezone,
            'delivery_time_local' => substr((string) $preference->delivery_time_local, 0, 5),
            'planning_balance' => (float) $preference->planning_balance,
            'risk_per_trade_percent' => (float) $preference->risk_per_trade_percent,
            'daily_risk_cap_percent' => (float) $preference->daily_risk_cap_percent,
            'max_trades' => (int) $preference->max_trades,
            'max_consecutive_losses' => (int) $preference->max_consecutive_losses,
            'minimum_rr' => (float) $preference->minimum_rr,
            'markets' => (array) $preference->markets_json,
            'sessions' => (array) $preference->sessions_json,
            'whatsapp_pdf_enabled' => (bool) $preference->whatsapp_pdf_enabled,
        ];

        try {
            return TradingRoutineDailyPlan::query()->create([
                'user_id' => $user->id,
                'plan_date' => $planDate,
                'customer_name_snapshot' => $user->name,
                'customer_email_snapshot' => $user->email,
                'whatsapp_number_snapshot' => $user->whatsapp_verified_at !== null
                    ? $user->whatsapp_number
                    : null,
                'timezone_snapshot' => $timezone,
                'preference_snapshot_json' => $preferenceSnapshot,
                'plan_json' => $this->planner->build($preference, $now),
                'generated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            $winner = TradingRoutineDailyPlan::query()
                ->where('user_id', $user->id)
                ->whereDate('plan_date', $planDate)
                ->first();

            if ($winner) {
                return $winner;
            }

            throw $exception;
        }
    }
}
