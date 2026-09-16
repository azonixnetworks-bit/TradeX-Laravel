<?php

namespace App\Services\Trading;

use App\Models\EconomicEvent;
use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class FundedNoTradeZoneService
{
    /**
     * Resolve the highest-priority applicable funded-account rule for one event.
     */
    public function resolveRule(
        FundedAccountPartner $partner,
        EconomicEvent $event,
        ?string $programName = null,
        string $phase = FundedNoTradeRule::PHASE_ALL,
        ?CarbonInterface $at = null
    ): ?FundedNoTradeRule {
        $at = $at ?: CarbonImmutable::now('UTC');
        $phase = strtoupper(trim($phase ?: FundedNoTradeRule::PHASE_ALL));
        $programName = filled($programName) ? trim((string) $programName) : null;
        $impact = strtoupper((string) ($event->effectiveIntelligence()['impact'] ?? $event->impact));
        $currency = strtoupper(trim((string) $event->currency));

        return FundedNoTradeRule::query()
            ->where('funded_account_partner_id', $partner->id)
            ->where('is_active', true)
            ->with('partner')
            ->orderBy('priority')
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->get()
            ->first(function (FundedNoTradeRule $rule) use ($at, $phase, $programName, $impact, $currency): bool {
                if (! $rule->isEffectiveAt($at) || ! $rule->appliesToImpact($impact)) {
                    return false;
                }

                if ($rule->phase !== FundedNoTradeRule::PHASE_ALL && $rule->phase !== $phase) {
                    return false;
                }

                if ($rule->program_name !== null) {
                    if ($programName === null || strcasecmp($rule->program_name, $programName) !== 0) {
                        return false;
                    }
                }

                $restrictedCurrencies = array_values((array) $rule->restricted_currencies);
                if ($restrictedCurrencies !== [] && ! in_array($currency, $restrictedCurrencies, true)) {
                    return false;
                }

                return true;
            });
    }

    /**
     * Build a serializable UTC no-trade-zone payload shared by admin, API and PDF integrations.
     *
     * @return array<string, mixed>|null
     */
    public function buildZone(
        FundedAccountPartner $partner,
        EconomicEvent $event,
        ?string $programName = null,
        string $phase = FundedNoTradeRule::PHASE_ALL,
        ?CarbonInterface $now = null
    ): ?array {
        $now = CarbonImmutable::instance($now ?: CarbonImmutable::now('UTC'))->utc();
        $rule = $this->resolveRule($partner, $event, $programName, $phase, $now);

        if ($rule === null || $rule->policy === FundedNoTradeRule::POLICY_ALLOWED) {
            return null;
        }

        $scheduledAt = CarbonImmutable::instance($event->scheduled_at_utc)->utc();
        $startsAt = $scheduledAt->subMinutes(max(0, $rule->minutes_before));
        $endsAt = $scheduledAt->addMinutes(max(0, $rule->minutes_after));

        $status = match (true) {
            $now->gte($startsAt) && $now->lt($endsAt) => 'ACTIVE',
            $now->lt($startsAt) => 'UPCOMING',
            default => 'ENDED',
        };

        $eventPairs = $event->relationLoaded('pairs')
            ? $event->pairs->pluck('symbol')->map(fn ($symbol): string => strtoupper((string) $symbol))->values()->all()
            : $event->pairs()->pluck('symbol')->map(fn ($symbol): string => strtoupper((string) $symbol))->values()->all();

        $ruleInstruments = array_values((array) $rule->restricted_instruments);
        $instruments = $rule->affected_instruments_only
            ? ($ruleInstruments !== [] ? array_values(array_intersect($eventPairs, $ruleInstruments)) : $eventPairs)
            : $ruleInstruments;

        return [
            'status' => $status,
            'policy' => $rule->policy,
            'starts_at_utc' => $startsAt->toIso8601String(),
            'ends_at_utc' => $endsAt->toIso8601String(),
            'minutes_before' => $rule->minutes_before,
            'minutes_after' => $rule->minutes_after,
            'event_uuid' => $event->uuid,
            'event_title' => $event->title,
            'event_currency' => $event->currency,
            'event_impact' => strtoupper((string) ($event->effectiveIntelligence()['impact'] ?? $event->impact)),
            'event_scheduled_at_utc' => $scheduledAt->toIso8601String(),
            'affected_instruments' => $instruments,
            'partner_uuid' => $partner->uuid,
            'partner_name' => $partner->name,
            'program_name' => $programName,
            'phase' => strtoupper($phase),
            'rule_uuid' => $rule->uuid,
            'rule_summary' => $rule->rule_summary,
            'source_url' => $rule->source_url,
            'source_reference' => $rule->source_reference,
            'verified_at' => $rule->verified_at?->toIso8601String(),
            'block_open_new_trades' => $rule->block_open_new_trades,
            'block_close_trades' => $rule->block_close_trades,
            'block_pending_orders' => $rule->block_pending_orders,
        ];
    }
}
