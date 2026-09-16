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
        private readonly TradingRoutinePlannerService $planner,
        private readonly FundedNoTradeZoneCustomerService $fundedNoTradeZones
    ) {
    }

    public function createOrGet(
        User $user,
        TradingRoutinePreference $preference,
        ?CarbonImmutable $now = null
    ): TradingRoutineDailyPlan {
        $timezone = $preference->timezone ?: (string) config('trading_routine.defaults.timezone', 'Asia/Colombo');
        $nowUtc = $now ?: CarbonImmutable::now('UTC');
        $localNow = $nowUtc->setTimezone($timezone);
        $planDate = $localNow->toDateString();

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

        $planData = $this->planner->build($preference, $localNow);
        $fundedSnapshot = $this->fundedNoTradeZones->snapshotForUserDate(
            $user,
            $planDate,
            $timezone,
            $nowUtc
        );

        $planData['funded_no_trade_zone'] = $fundedSnapshot;
        $planData['no_trade_rules'] = array_values(array_slice(array_unique(array_merge(
            $this->fundedZoneRuleLines($fundedSnapshot),
            (array) ($planData['no_trade_rules'] ?? [])
        )), 0, 12));

        if (($fundedSnapshot['rule_check_required'] ?? false) === true) {
            $planData['warnings'] = array_values(array_unique(array_merge(
                (array) ($planData['warnings'] ?? []),
                ['Funded-account news rules are unverified or older than 30 days. Re-check the prop firm official rules before trading.']
            )));
        }

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
                'plan_json' => $planData,
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

    /**
     * The existing PDF already renders plan_json.no_trade_rules. By injecting
     * funded-company windows here, web/API/PDF consume one immutable daily snapshot.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<int, string>
     */
    private function fundedZoneRuleLines(array $snapshot): array
    {
        $lines = [];

        foreach ((array) ($snapshot['zones'] ?? []) as $zone) {
            if (! is_array($zone)) {
                continue;
            }

            $instruments = implode(', ', (array) ($zone['affected_instruments'] ?? []));
            $verification = ($zone['rule_verification_status'] ?? 'UNVERIFIED') === 'VERIFIED'
                ? ''
                : ' | RULE CHECK REQUIRED';

            $lines[] = sprintf(
                'FUNDED NO TRADE %s %s | %s (%s %s) | %s%s',
                (string) ($zone['local_window_label'] ?? ''),
                (string) ($zone['timezone'] ?? ''),
                (string) ($zone['event_title'] ?? 'Economic event'),
                (string) ($zone['event_currency'] ?? ''),
                (string) ($zone['event_impact'] ?? ''),
                $instruments !== '' ? $instruments : 'Affected instruments per firm rule',
                $verification
            );
        }

        return array_values(array_slice($lines, 0, 8));
    }
}
