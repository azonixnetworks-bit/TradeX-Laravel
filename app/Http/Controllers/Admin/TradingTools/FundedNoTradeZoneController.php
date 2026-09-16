<?php

namespace App\Http\Controllers\Admin\TradingTools;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\EconomicEvent;
use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FundedNoTradeZoneController extends Controller
{
    public function index(): View
    {
        $admin = $this->viewer();
        $now = now('UTC');

        return view('admin.trading_tools.funded_no_trade_zone.index', [
            'rules' => FundedNoTradeRule::query()
                ->with('partner')
                ->orderByDesc('is_active')
                ->orderBy('priority')
                ->orderBy('partner_id')
                ->paginate(25),
            'activeRuleCount' => FundedNoTradeRule::query()->where('is_active', true)->count(),
            'partnerRuleCount' => FundedNoTradeRule::query()->distinct('funded_account_partner_id')->count('funded_account_partner_id'),
            'needsVerificationCount' => FundedNoTradeRule::query()
                ->where('is_active', true)
                ->where(function ($query) use ($now): void {
                    $query->whereNull('verified_at')
                        ->orWhere('verified_at', '<', $now->copy()->subDays(30));
                })
                ->count(),
            'upcomingHighImpactCount' => EconomicEvent::query()
                ->where('impact', EconomicEvent::IMPACT_HIGH)
                ->where('scheduled_at_utc', '>=', $now)
                ->where('scheduled_at_utc', '<=', $now->copy()->addDay())
                ->count(),
            'canManageRules' => $admin->hasPermission('funded_no_trade_zone.rules.manage'),
        ]);
    }

    public function create(): View
    {
        $this->manager();

        return view('admin.trading_tools.funded_no_trade_zone.create', [
            'partners' => $this->partners(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->manager();
        $attributes = $this->validatedAttributes($request);

        $rule = DB::transaction(function () use ($request, $admin, $attributes): FundedNoTradeRule {
            $rule = FundedNoTradeRule::query()->create(array_merge($attributes, [
                'created_by_admin_id' => $admin->id,
                'updated_by_admin_id' => $admin->id,
            ]));

            $this->audit($request, $admin, 'FUNDED_NO_TRADE_RULE_CREATED', $rule, null, $this->auditSnapshot($rule));

            return $rule;
        });

        return redirect()
            ->route('admin.funded_no_trade_zone.rules.edit', $rule)
            ->with('success', 'Funded no-trade rule created.');
    }

    public function edit(FundedNoTradeRule $fundedNoTradeRule): View
    {
        $this->manager();

        return view('admin.trading_tools.funded_no_trade_zone.edit', [
            'rule' => $fundedNoTradeRule->load('partner'),
            'partners' => $this->partners(),
        ]);
    }

    public function update(Request $request, FundedNoTradeRule $fundedNoTradeRule): RedirectResponse
    {
        $admin = $this->manager();
        $attributes = $this->validatedAttributes($request);

        DB::transaction(function () use ($request, $admin, $attributes, $fundedNoTradeRule): void {
            $locked = FundedNoTradeRule::query()->lockForUpdate()->findOrFail($fundedNoTradeRule->id);
            $old = $this->auditSnapshot($locked);

            $locked->fill(array_merge($attributes, [
                'updated_by_admin_id' => $admin->id,
            ]))->save();

            $this->audit($request, $admin, 'FUNDED_NO_TRADE_RULE_UPDATED', $locked, $old, $this->auditSnapshot($locked));
        });

        return back()->with('success', 'Funded no-trade rule updated.');
    }

    public function destroy(Request $request, FundedNoTradeRule $fundedNoTradeRule): RedirectResponse
    {
        $admin = $this->manager();

        DB::transaction(function () use ($request, $admin, $fundedNoTradeRule): void {
            $locked = FundedNoTradeRule::query()->lockForUpdate()->findOrFail($fundedNoTradeRule->id);
            $old = $this->auditSnapshot($locked);
            $locked->delete();

            $this->audit($request, $admin, 'FUNDED_NO_TRADE_RULE_DELETED', $locked, $old, null);
        });

        return redirect()
            ->route('admin.funded_no_trade_zone.index')
            ->with('success', 'Funded no-trade rule archived.');
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(Request $request): array
    {
        $validated = $request->validate([
            'funded_account_partner_id' => ['required', 'integer', 'exists:funded_account_partners,id'],
            'program_name' => ['nullable', 'string', 'max:160'],
            'phase' => ['required', Rule::in([
                FundedNoTradeRule::PHASE_ALL,
                FundedNoTradeRule::PHASE_CHALLENGE,
                FundedNoTradeRule::PHASE_VERIFICATION,
                FundedNoTradeRule::PHASE_FUNDED,
            ])],
            'policy' => ['required', Rule::in([
                FundedNoTradeRule::POLICY_RESTRICTED,
                FundedNoTradeRule::POLICY_ALLOWED,
                FundedNoTradeRule::POLICY_CONDITIONAL,
            ])],
            'enforcement_type' => ['required', Rule::in([
                FundedNoTradeRule::ENFORCEMENT_BLOCKED,
                FundedNoTradeRule::ENFORCEMENT_HARD_BREACH,
                FundedNoTradeRule::ENFORCEMENT_SOFT_BREACH,
                FundedNoTradeRule::ENFORCEMENT_PROFIT_DEDUCTION,
                FundedNoTradeRule::ENFORCEMENT_REWARD_SHARE,
                FundedNoTradeRule::ENFORCEMENT_GUIDANCE,
            ])],
            'impact_levels' => ['required', 'array', 'min:1'],
            'impact_levels.*' => ['required', Rule::in([
                EconomicEvent::IMPACT_HIGH,
                EconomicEvent::IMPACT_MEDIUM,
                EconomicEvent::IMPACT_LOW,
            ])],
            'event_keywords' => ['nullable', 'string', 'max:5000'],
            'minutes_before' => ['required', 'integer', 'min:0', 'max:180'],
            'minutes_after' => ['required', 'integer', 'min:0', 'max:180'],
            'affected_instruments_only' => ['required', 'boolean'],
            'restricted_currencies' => ['nullable', 'string', 'max:2000'],
            'restricted_instruments' => ['nullable', 'string', 'max:4000'],
            'block_open_new_trades' => ['required', 'boolean'],
            'block_close_trades' => ['required', 'boolean'],
            'block_pending_orders' => ['required', 'boolean'],
            'rule_summary' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'url:http,https', 'max:2048'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'verified_at' => ['nullable', 'date'],
            'effective_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['required', 'boolean'],
            'priority' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        return [
            'funded_account_partner_id' => (int) $validated['funded_account_partner_id'],
            'program_name' => filled($validated['program_name'] ?? null) ? trim((string) $validated['program_name']) : null,
            'phase' => strtoupper((string) $validated['phase']),
            'policy' => strtoupper((string) $validated['policy']),
            'enforcement_type' => strtoupper((string) $validated['enforcement_type']),
            'impact_levels' => array_values(array_unique(array_map('strtoupper', (array) $validated['impact_levels']))),
            'event_keywords' => $this->listValue($validated['event_keywords'] ?? null),
            'minutes_before' => (int) $validated['minutes_before'],
            'minutes_after' => (int) $validated['minutes_after'],
            'affected_instruments_only' => (bool) $validated['affected_instruments_only'],
            'restricted_currencies' => $this->listValue($validated['restricted_currencies'] ?? null),
            'restricted_instruments' => $this->listValue($validated['restricted_instruments'] ?? null),
            'block_open_new_trades' => (bool) $validated['block_open_new_trades'],
            'block_close_trades' => (bool) $validated['block_close_trades'],
            'block_pending_orders' => (bool) $validated['block_pending_orders'],
            'rule_summary' => filled($validated['rule_summary'] ?? null) ? trim((string) $validated['rule_summary']) : null,
            'source_url' => $validated['source_url'] ?? null,
            'source_reference' => filled($validated['source_reference'] ?? null) ? trim((string) $validated['source_reference']) : null,
            'verified_at' => $validated['verified_at'] ?? null,
            'effective_from' => $validated['effective_from'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'priority' => (int) $validated['priority'],
        ];
    }

    /** @return array<int, string>|null */
    private function listValue(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: [];
        $items = array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => strtoupper(trim($item)),
            $items
        ), static fn (string $item): bool => $item !== '')));

        return $items === [] ? null : $items;
    }

    private function viewer(): AdminUser
    {
        $admin = $this->admin();
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('funded_no_trade_zone.view'),
            403
        );

        return $admin;
    }

    private function manager(): AdminUser
    {
        $admin = $this->admin();
        abort_unless(
            $admin->isSuperAdmin() || $admin->hasPermission('funded_no_trade_zone.rules.manage'),
            403
        );

        return $admin;
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function partners()
    {
        return FundedAccountPartner::query()
            ->orderByDesc('is_active')
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'is_active', 'challenge_types']);
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(FundedNoTradeRule $rule): array
    {
        return [
            'partner_id' => $rule->funded_account_partner_id,
            'program_name' => $rule->program_name,
            'phase' => $rule->phase,
            'policy' => $rule->policy,
            'enforcement_type' => $rule->enforcement_type,
            'impact_levels' => $rule->impact_levels,
            'event_keywords' => $rule->event_keywords,
            'minutes_before' => $rule->minutes_before,
            'minutes_after' => $rule->minutes_after,
            'is_active' => $rule->is_active,
            'priority' => $rule->priority,
            'verified_at' => $rule->verified_at?->toIso8601String(),
        ];
    }

    private function audit(
        Request $request,
        AdminUser $admin,
        string $action,
        FundedNoTradeRule $rule,
        ?array $old,
        ?array $new
    ): void {
        AuditLog::record(
            'ADMIN',
            $admin->id,
            $action,
            'FUNDED_NO_TRADE_ZONE',
            FundedNoTradeRule::class,
            $rule->id,
            $old,
            $new,
            $request->ip(),
            $request->userAgent()
        );
    }
}
