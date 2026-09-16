<?php

namespace App\Http\Controllers\Api\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TradingRoutineDailyPlan;
use App\Models\TradingRoutinePreference;
use App\Models\User;
use App\Services\Trading\TradingRoutineDailyPlanService;
use App\Services\Trading\TradingRoutinePdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class TradingRoutineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $preference = TradingRoutinePreference::query()->where('user_id', $user->id)->first();

        $recentPlans = TradingRoutineDailyPlan::query()
            ->where('user_id', $user->id)
            ->with('delivery')
            ->latest('plan_date')
            ->limit(10)
            ->get()
            ->map(fn (TradingRoutineDailyPlan $plan): array => $this->summary($plan))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'whatsapp_verified' => $user->whatsapp_verified_at !== null,
                    'whatsapp_destination' => $this->maskPhone($user->whatsapp_number),
                ],
                'preference' => $this->preferenceData($preference),
                'recent_plans' => $recentPlans,
                'defaults' => config('trading_routine.defaults'),
                'guardrails' => config('trading_routine.guardrails'),
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $validated = $request->validate($this->preferenceRules());

        if (
            (bool) $validated['whatsapp_pdf_enabled']
            && $user->whatsapp_verified_at === null
        ) {
            throw ValidationException::withMessages([
                'whatsapp_pdf_enabled' => 'Verify your WhatsApp number before enabling morning PDF delivery.',
            ]);
        }

        $preference = TradingRoutinePreference::query()->where('user_id', $user->id)->first();
        $wasWhatsAppEnabled = (bool) ($preference?->whatsapp_pdf_enabled ?? false);

        $attributes = [
            'enabled' => (bool) $validated['enabled'],
            'timezone' => $validated['timezone'],
            'delivery_time_local' => $validated['delivery_time_local'].':00',
            'planning_balance' => (float) $validated['planning_balance'],
            'risk_per_trade_percent' => (float) $validated['risk_per_trade_percent'],
            'daily_risk_cap_percent' => (float) $validated['daily_risk_cap_percent'],
            'max_trades' => (int) $validated['max_trades'],
            'max_consecutive_losses' => (int) $validated['max_consecutive_losses'],
            'minimum_rr' => (float) $validated['minimum_rr'],
            'markets_json' => array_values($validated['markets']),
            'sessions_json' => array_values($validated['sessions']),
            'pre_market_checklist_json' => array_values($validated['pre_market_checklist']),
            'no_trade_rules_json' => array_values($validated['no_trade_rules']),
            'focus_statement' => trim((string) $validated['focus_statement']),
            'whatsapp_pdf_enabled' => (bool) $validated['whatsapp_pdf_enabled'],
        ];

        if ((bool) $validated['whatsapp_pdf_enabled'] && ! $wasWhatsAppEnabled) {
            $attributes['whatsapp_opted_in_at'] = now();
        }

        $preference = TradingRoutinePreference::query()->updateOrCreate(
            ['user_id' => $user->id],
            $attributes
        );

        AuditLog::record(
            'USER',
            $user->id,
            'TRADING_ROUTINE_PREFERENCES_UPDATED',
            'TRADING_TOOLS',
            TradingRoutinePreference::class,
            $preference->id,
            null,
            [
                'enabled' => $preference->enabled,
                'timezone' => $preference->timezone,
                'delivery_time_local' => substr((string) $preference->delivery_time_local, 0, 5),
                'whatsapp_pdf_enabled' => $preference->whatsapp_pdf_enabled,
            ],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'data' => $this->preferenceData($preference),
        ]);
    }

    public function today(
        Request $request,
        TradingRoutineDailyPlanService $plans
    ): JsonResponse {
        $user = $this->user($request);
        $preference = TradingRoutinePreference::query()
            ->where('user_id', $user->id)
            ->where('enabled', true)
            ->first();

        if (! $preference) {
            return response()->json([
                'success' => false,
                'message' => 'Enable your Daily Trading Routine before generating today\'s plan.',
            ], 409);
        }

        $plan = $plans->createOrGet($user, $preference);
        $plan->load('delivery');

        return response()->json([
            'success' => true,
            'data' => $this->detail($plan),
        ]);
    }

    public function show(
        Request $request,
        TradingRoutineDailyPlan $tradingRoutineDailyPlan
    ): JsonResponse {
        $user = $this->user($request);
        $this->ownedBy($tradingRoutineDailyPlan, $user);
        $tradingRoutineDailyPlan->load('delivery');

        return response()->json([
            'success' => true,
            'data' => $this->detail($tradingRoutineDailyPlan),
        ]);
    }

    public function pdf(
        Request $request,
        TradingRoutineDailyPlan $tradingRoutineDailyPlan,
        TradingRoutinePdfService $pdf
    ): SymfonyResponse {
        $user = $this->user($request);
        $this->ownedBy($tradingRoutineDailyPlan, $user);
        $filename = 'tradex-daily-trading-plan-'.$tradingRoutineDailyPlan->plan_date?->format('Y-m-d').'.pdf';

        return Response::make($pdf->render($tradingRoutineDailyPlan), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }

    /** @return array<string, mixed> */
    private function preferenceRules(): array
    {
        $maxRisk = (float) config('trading_routine.guardrails.max_risk_per_trade_percent', 1.0);
        $maxDaily = (float) config('trading_routine.guardrails.max_daily_risk_cap_percent', 2.0);
        $maxTrades = (int) config('trading_routine.guardrails.max_trades', 5);
        $maxLosses = (int) config('trading_routine.guardrails.max_consecutive_losses', 3);

        return [
            'customer_name' => ['prohibited'],
            'customer_email' => ['prohibited'],
            'whatsapp_number' => ['prohibited'],
            'enabled' => ['required', 'boolean'],
            'timezone' => ['required', 'timezone'],
            'delivery_time_local' => ['required', 'date_format:H:i'],
            'planning_balance' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'risk_per_trade_percent' => ['required', 'numeric', 'gt:0', 'max:'.$maxRisk],
            'daily_risk_cap_percent' => ['required', 'numeric', 'gt:0', 'max:'.$maxDaily],
            'max_trades' => ['required', 'integer', 'min:1', 'max:'.$maxTrades],
            'max_consecutive_losses' => ['required', 'integer', 'min:1', 'max:'.$maxLosses],
            'minimum_rr' => ['required', 'numeric', 'min:1', 'max:10'],
            'markets' => ['required', 'array', 'min:1', 'max:5'],
            'markets.*' => ['required', Rule::in(['FOREX', 'CRYPTO', 'COMMODITY', 'DERIV', 'STOCKS'])],
            'sessions' => ['required', 'array', 'min:1', 'max:4'],
            'sessions.*' => ['required', Rule::in(['ASIA', 'LONDON', 'NEW_YORK', 'CUSTOM'])],
            'pre_market_checklist' => ['required', 'array', 'min:1', 'max:8'],
            'pre_market_checklist.*' => ['required', 'string', 'max:220'],
            'no_trade_rules' => ['required', 'array', 'min:1', 'max:8'],
            'no_trade_rules.*' => ['required', 'string', 'max:220'],
            'focus_statement' => ['required', 'string', 'max:500'],
            'whatsapp_pdf_enabled' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    private function preferenceData(?TradingRoutinePreference $preference): array
    {
        $defaults = (array) config('trading_routine.defaults', []);

        return [
            'enabled' => (bool) ($preference?->enabled ?? false),
            'timezone' => $preference?->timezone ?? ($defaults['timezone'] ?? 'Asia/Colombo'),
            'delivery_time_local' => substr((string) ($preference?->delivery_time_local ?? ($defaults['delivery_time_local'] ?? '06:00')), 0, 5),
            'planning_balance' => (float) ($preference?->planning_balance ?? ($defaults['planning_balance'] ?? 10000)),
            'risk_per_trade_percent' => (float) ($preference?->risk_per_trade_percent ?? ($defaults['risk_per_trade_percent'] ?? 0.5)),
            'daily_risk_cap_percent' => (float) ($preference?->daily_risk_cap_percent ?? ($defaults['daily_risk_cap_percent'] ?? 1.0)),
            'max_trades' => (int) ($preference?->max_trades ?? ($defaults['max_trades'] ?? 3)),
            'max_consecutive_losses' => (int) ($preference?->max_consecutive_losses ?? ($defaults['max_consecutive_losses'] ?? 2)),
            'minimum_rr' => (float) ($preference?->minimum_rr ?? ($defaults['minimum_rr'] ?? 2.0)),
            'markets' => (array) ($preference?->markets_json ?? ($defaults['markets'] ?? ['FOREX'])),
            'sessions' => (array) ($preference?->sessions_json ?? ($defaults['sessions'] ?? ['LONDON', 'NEW_YORK'])),
            'pre_market_checklist' => (array) ($preference?->pre_market_checklist_json ?? config('trading_routine.default_pre_market_checklist', [])),
            'no_trade_rules' => (array) ($preference?->no_trade_rules_json ?? config('trading_routine.default_no_trade_rules', [])),
            'focus_statement' => $preference?->focus_statement ?: (string) config('trading_routine.default_focus_statement'),
            'whatsapp_pdf_enabled' => (bool) ($preference?->whatsapp_pdf_enabled ?? false),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(TradingRoutineDailyPlan $plan): array
    {
        $data = (array) $plan->plan_json;

        return [
            'uuid' => $plan->uuid,
            'plan_date' => $plan->plan_date?->format('Y-m-d'),
            'headline' => $data['headline'] ?? null,
            'risk_per_trade_amount' => $data['risk_per_trade_amount'] ?? null,
            'daily_risk_cap_amount' => $data['daily_risk_cap_amount'] ?? null,
            'max_trades' => $data['max_trades'] ?? null,
            'delivery_status' => $plan->delivery?->status,
            'generated_at' => $plan->generated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(TradingRoutineDailyPlan $plan): array
    {
        return [
            'uuid' => $plan->uuid,
            'plan_date' => $plan->plan_date?->format('Y-m-d'),
            'customer' => [
                'name' => $plan->customer_name_snapshot,
                'email' => $plan->customer_email_snapshot,
            ],
            'plan' => (array) $plan->plan_json,
            'delivery' => $plan->delivery ? [
                'channel' => $plan->delivery->channel,
                'status' => $plan->delivery->status,
                'attempts' => $plan->delivery->attempts,
                'failure_code' => $plan->delivery->failure_code,
                'accepted_at' => $plan->delivery->accepted_at?->toISOString(),
            ] : null,
            'pdf_path' => route('api.v1.trading-routine.plans.pdf', $plan, false),
            'generated_at' => $plan->generated_at?->toISOString(),
        ];
    }

    private function ownedBy(TradingRoutineDailyPlan $plan, User $user): void
    {
        abort_unless((int) $plan->user_id === (int) $user->id, 404);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, 'Unauthenticated.');

        return $user;
    }

    private function maskPhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $phone = (string) $phone;
        return mb_substr($phone, 0, 4).'***'.mb_substr($phone, -3);
    }
}
